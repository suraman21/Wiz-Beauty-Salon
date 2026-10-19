<?php
declare(strict_types=1);

/**
 * Housekeeping CLI.
 *
 *   php bin/housekeeping.php
 *
 * Removes what has aged out: rate-limit windows, old login attempts, audit rows
 * past the retention period and rotated log files. None of it is urgent on the
 * day it is written, which is exactly why it needs a scheduled job — otherwise
 * the tables grow forever and nobody notices until they are large.
 *
 * Suggested cron entry (the deploy cron runs every two minutes; this does not
 * belong there):
 *
 *   15 3 * * *  /opt/cpanel/ea-php84/root/usr/bin/php /home/arkeonet/wiz-app/bin/housekeeping.php >> /home/arkeonet/logs/wiz-housekeeping.log 2>&1
 */

use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Repositories\LoginAttemptRepository;
use Wiz\Repositories\RateLimitRepository;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$config = new Config($root . '/config');

// The web front controller sets this; a CLI run has only php.ini, which on this
// host is Asia/Dubai. Without it the log file is named for the wrong day.
$appConfig = $config->file('app');
date_default_timezone_set((string) ($appConfig['timezone'] ?? 'Africa/Addis_Ababa'));

$databaseConfig = $config->file('database');

if ($databaseConfig === []) {
    fwrite(STDERR, "No database configuration found in config/database.php\n");
    exit(1);
}

$db = new Database($databaseConfig);

$rateLimits = new RateLimitRepository($db);
$loginAttempts = new LoginAttemptRepository($db);

$auditDays = max(30, (int) (Env::get('AUDIT_RETENTION_DAYS', '365') ?? 365));
$logDays = max(7, (int) (Env::get('LOG_RETENTION_DAYS', '90') ?? 90));

$removed = [
    'rate_limits' => $rateLimits->prune(7),
    'login_attempts' => $loginAttempts->prune(30),
    'activity_log' => $db->execute(
        'DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL :days DAY)',
        [':days' => $auditDays],
    ),
];

$logDirectory = $root . '/storage/logs';
$cutoff = time() - ($logDays * 86400);
$logsRemoved = 0;

foreach (glob($logDirectory . '/app-*.log') ?: [] as $file) {
    $modified = @filemtime($file);

    if ($modified !== false && $modified < $cutoff && @unlink($file)) {
        $logsRemoved++;
    }
}

foreach ($removed as $table => $count) {
    printf("%-16s %6d removed\n", $table, $count);
}

printf("%-16s %6d removed\n", 'log files', $logsRemoved);
