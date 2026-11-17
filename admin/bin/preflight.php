<?php
declare(strict_types=1);

/**
 * Pre-deploy check.
 *
 *   php bin/preflight.php
 *
 * Run this on the server after uploading a release and before telling the salon
 * it is live. It checks the things that fail silently: a missing PHP extension
 * that only shows up the first time someone uploads a photo, an `.env` the web
 * server cannot read, a database the app cannot reach, a migration nobody ran, a
 * storage directory that is not writable, an application key that is missing.
 *
 * Exit code 0 means every check passed; 1 means at least one FAIL. Warnings are
 * printed but do not fail the run — they are worth knowing on the day, not
 * blockers.
 *
 * This script reads only. It writes nothing, migrates nothing, and never starts a
 * session.
 */

namespace Wiz\Bin;

use Throwable;
use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

/**
 * Counts and prints. Kept in a class so PHPStan can see the counters' types;
 * globals in a CLI script are not worth the ambiguity.
 */
final class Preflight
{
    private int $failures = 0;

    private int $warnings = 0;

    public function heading(string $title): void
    {
        echo "\n" . $title . "\n";
    }

    public function check(string $label, bool $ok, string $detail = '', bool $warnOnly = false): void
    {
        if ($ok) {
            printf("  ok    %s%s\n", $label, $this->suffix($detail));

            return;
        }

        if ($warnOnly) {
            $this->warnings++;
            printf("  WARN  %s%s\n", $label, $this->suffix($detail));

            return;
        }

        $this->failures++;
        printf("  FAIL  %s%s\n", $label, $this->suffix($detail));
    }

    public function summary(): int
    {
        echo "\n" . str_repeat('-', 46) . "\n";
        printf("%d failure(s), %d warning(s)\n", $this->failures, $this->warnings);
        echo $this->failures === 0 ? "Ready.\n" : "Not ready — fix the failures above.\n";

        return $this->failures === 0 ? 0 : 1;
    }

    private function suffix(string $detail): string
    {
        return $detail === '' ? '' : ' — ' . $detail;
    }
}

$check = new Preflight();

echo "WIZ Beauty Salon — pre-deploy check\n";
echo str_repeat('-', 46) . "\n";

// -------------------------------------------------------------------- PHP
$check->heading('PHP');

$check->check('PHP 8.4 or newer', PHP_VERSION_ID >= 80400, PHP_VERSION);

// Exactly what composer.json requires, plus dom: HTML Purifier needs it and
// declares it only transitively, so a host without it publishes broken email
// designs and nothing else complains.
foreach (['pdo', 'pdo_mysql', 'gd', 'mbstring', 'fileinfo', 'sodium', 'json', 'dom'] as $extension) {
    $check->check('extension ' . $extension, extension_loaded($extension));
}

$uploadIni = (string) ini_get('upload_max_filesize');
$postIni = (string) ini_get('post_max_size');

$check->check(
    'upload_max_filesize >= 5M',
    iniBytes($uploadIni) >= 5 * 1024 * 1024 || $uploadIni === '0',
    $uploadIni,
    true,
);
$check->check(
    'post_max_size leaves room above the upload limit',
    $postIni === '0' || iniBytes($postIni) > iniBytes($uploadIni),
    $postIni,
    true,
);

/** "8M", "512K", "1G", "0" → bytes. */
function iniBytes(string $value): int
{
    $value = trim($value);

    if ($value === '' || $value === '0') {
        return 0;
    }

    $number = (int) $value;
    $unit = strtolower(substr($value, -1));

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

// ------------------------------------------------------------ environment
$check->heading('Environment');

$envFile = $root . '/.env';
$check->check('.env exists', is_file($envFile), $envFile);

if (is_file($envFile)) {
    $mode = fileperms($envFile);
    $check->check(
        '.env is not group- or world-readable',
        $mode !== false && ($mode & 0o077) === 0,
        $mode === false ? 'cannot stat' : sprintf('%04o', $mode & 0o777),
        true,
    );
}

Env::load($envFile);

$check->check(
    'APP_KEY is set and 64 hex characters',
    (bool) preg_match('/^[0-9a-f]{64}$/i', (string) Env::get('APP_KEY', '')),
    'signs the customer links in already-sent mail',
);
$check->check('APP_ENV is production', Env::get('APP_ENV', '') === 'production', (string) Env::get('APP_ENV', ''), true);
$check->check('APP_DEBUG is off', Env::get('APP_DEBUG', 'false') !== 'true', 'APP_DEBUG=' . (string) Env::get('APP_DEBUG', 'false'));
$check->check('SESSION_SECURE is on', Env::get('SESSION_SECURE', 'true') !== 'false', 'SESSION_SECURE=' . (string) Env::get('SESSION_SECURE', 'true'));

$uploadPath = (string) Env::get('UPLOAD_PATH', '');
$check->check('UPLOAD_PATH is set', $uploadPath !== '', $uploadPath);
$check->check(
    'UPLOAD_PATH exists and is writable',
    $uploadPath !== '' && is_dir($uploadPath) && is_writable($uploadPath),
    $uploadPath,
);

foreach (['storage/logs', 'storage/sessions', 'storage/cache'] as $relative) {
    $path = $root . '/' . $relative;
    $check->check($relative . ' is writable', is_dir($path) && is_writable($path), $path);
}

// --------------------------------------------------------------- database
$check->heading('Database');

try {
    $config = new Config($root . '/config');

    /** @var array<string, mixed> $dbConfig */
    $dbConfig = $config->file('database');

    $db = new Database($dbConfig);

    $check->check('connection', true, (string) $db->scalar('SELECT VERSION()'));

    $applied = (int) $db->scalar('SELECT COUNT(*) FROM migrations');
    $files = glob($root . '/database/migrations/*.php');
    $expected = is_array($files) ? count($files) : 0;

    $check->check('every migration is applied', $applied === $expected, $applied . ' of ' . $expected . ' applied');

    $admins = (int) $db->scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    $check->check('at least one active administrator', $admins > 0, $admins . ' found');

    $categories = (int) $db->scalar('SELECT COUNT(*) FROM service_categories');
    $check->check('the catalogue has categories', $categories > 0, $categories . ' categories', true);

    $window = $db->scalar("SELECT setting_value FROM settings WHERE setting_key = 'booking.change_window_hours'");
    $check->check(
        'the change window is set',
        $window !== null && $window !== '',
        'booking.change_window_hours=' . ($window === null || $window === '' ? 'unset' : (string) $window),
        true,
    );
} catch (Throwable $e) {
    $check->check('connection', false, $e->getMessage());
}

// ---------------------------------------------------------------- release
$check->heading('Release');

$check->check('vendor/ is present (PHPMailer, HTML Purifier)', is_dir($root . '/vendor'));
$check->check('tests/ is absent from a release tree', !is_dir($root . '/tests'), 'present means this is a source checkout', true);
$check->check(
    'public/index.php is the only entry point',
    is_file($root . '/public/index.php') && !is_file($root . '/index.php'),
    'a stray index.php above public/ would be reachable without a rewrite',
);

$releaseFile = $root . '/RELEASE';
$check->check(
    'RELEASE names the commit this tree came from',
    is_file($releaseFile),
    is_file($releaseFile) ? trim((string) file_get_contents($releaseFile)) : 'not a release tree',
    true,
);

exit($check->summary());
