<?php
declare(strict_types=1);

/**
 * Creates the first administrator account.
 *
 *   php database/seed.php "Full Name" you@example.com 'a strong password'
 *
 * Values fall back to ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD from .env. If no
 * password is supplied one is generated and printed once — nothing is ever
 * written to the log. Running it again with an existing email is refused unless
 * --force is passed, which resets that account's password instead.
 */

use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Repositories\UserRepository;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--force'));

$name = $args[0] ?? Env::get('ADMIN_NAME', 'Salon Administrator');
$email = $args[1] ?? Env::get('ADMIN_EMAIL', '');
$password = $args[2] ?? Env::get('ADMIN_PASSWORD', '');

$name = trim((string) $name);
$email = mb_strtolower(trim((string) $email));

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "A valid administrator email is required.\n");
    fwrite(STDERR, "Usage: php database/seed.php \"Full Name\" you@example.com ['password'] [--force]\n");
    exit(64);
}

if ($name === '') {
    $name = 'Salon Administrator';
}

$generated = false;

if ($password === '') {
    $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'AB'), '=');
    $generated = true;
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Choose a password with at least 10 characters.\n");
    exit(64);
}

$config = new Config($root . '/config');

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = $config->file('database');

$db = new Database($databaseConfig);

try {
    $db->pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to the database. Check the DB_* values in .env\n");
    fwrite(STDERR, 'Driver said: ' . $e->getMessage() . "\n");
    exit(1);
}

$users = new UserRepository($db);
$existing = $users->findActiveByEmail($email);
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($existing !== null) {
    if (!$force) {
        fwrite(STDERR, "An account already exists for {$email}. Use --force to reset its password.\n");
        exit(1);
    }

    $users->updatePassword((int) $existing['id'], $hash);

    fwrite(STDOUT, "Password reset for {$email}.\n");

    if ($generated) {
        fwrite(STDOUT, "New password (shown once): {$password}\n");
    }

    exit(0);
}

$id = $users->create($name, $email, $hash, 'admin');

fwrite(STDOUT, "Administrator created.\n");
fwrite(STDOUT, "  id:    {$id}\n");
fwrite(STDOUT, "  name:  {$name}\n");
fwrite(STDOUT, "  email: {$email}\n");

if ($generated) {
    fwrite(STDOUT, "  password: {$password}\n");
    fwrite(STDOUT, "Sign in at /admin/login and change this password immediately.\n");
}

exit(0);
