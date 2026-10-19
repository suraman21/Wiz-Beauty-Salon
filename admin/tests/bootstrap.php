<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader when present. Tests that need the container or
 * database are expected to set up their own fixtures — the suite must be able to
 * run on a machine without a configured .env.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are not installed. Run: composer install\n");
    exit(1);
}

require $autoload;
