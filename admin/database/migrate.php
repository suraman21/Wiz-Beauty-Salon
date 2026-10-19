<?php
declare(strict_types=1);

/**
 * Migration CLI.
 *
 *   php database/migrate.php status     show applied and pending migrations
 *   php database/migrate.php migrate    apply everything pending
 *   php database/migrate.php rollback   undo the most recent batch
 *
 * Reads credentials from admin/.env, which lives outside the webroot. The deploy
 * pipeline runs `migrate` after every release, so a schema change ships with the
 * code that needs it.
 */

use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Core\Migrations;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$command = $argv[1] ?? 'status';

if (!in_array($command, ['status', 'migrate', 'rollback'], true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    fwrite(STDERR, "Usage: php database/migrate.php [status|migrate|rollback]\n");
    exit(64);
}

$config = new Config($root . '/config');
$databaseConfig = $config->file('database');

/** @var array<string, mixed> $databaseConfig */
$db = new Database($databaseConfig);

try {
    $db->pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to the database.\n");
    fwrite(STDERR, 'Check DB_HOST, DB_NAME, DB_USER and DB_PASS in ' . $root . "/.env\n");
    fwrite(STDERR, 'Driver said: ' . $e->getMessage() . "\n");
    exit(1);
}

$migrations = new Migrations($db, $root . '/database/migrations');

try {
    switch ($command) {
        case 'migrate':
            $applied = $migrations->migrate(static function (string $file): void {
                fwrite(STDOUT, "  applied  {$file}\n");
            });

            fwrite(STDOUT, $applied === []
                ? "Nothing to migrate — schema is up to date.\n"
                : sprintf("Done. %d migration(s) applied.\n", count($applied)));
            break;

        case 'rollback':
            $undone = $migrations->rollbackLastBatch();

            fwrite(STDOUT, $undone === []
                ? "Nothing to roll back.\n"
                : sprintf("Rolled back %d migration(s):\n  %s\n", count($undone), implode("\n  ", $undone)));
            break;

        default:
            $rows = $migrations->status();

            if ($rows === []) {
                fwrite(STDOUT, "No migration files found.\n");
                break;
            }

            foreach ($rows as $row) {
                printf(
                    "  %-9s %-44s %s\n",
                    strtoupper($row['status']),
                    $row['file'],
                    $row['applied_at'] ?? '',
                );
            }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
