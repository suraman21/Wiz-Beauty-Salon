<?php
declare(strict_types=1);

/**
 * Autoloader.
 *
 * Prefers Composer when it is installed (CI, local development). On the server
 * there is no Composer — the application has no runtime dependencies in the
 * foundation phase — so the same PSR-4 mapping is registered by hand. Both paths
 * load the helper functions, so templates behave identically either way.
 */

$root = dirname(__DIR__);
$composer = $root . '/vendor/autoload.php';

if (is_file($composer)) {
    require $composer;

    if (is_file($root . '/src/Support/helpers.php')) {
        require_once $root . '/src/Support/helpers.php';
    }

    return;
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Wiz\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require_once $root . '/src/Support/helpers.php';
