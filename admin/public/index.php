<?php
declare(strict_types=1);

/**
 * Front controller.
 *
 * Every request that reaches PHP — /admin and /api/v1 — arrives here. The file
 * lives in the application directory, outside the webroot; the docroot holds a
 * one-line shim that requires this file.
 */

use Wiz\Core\Kernel;
use Wiz\Core\Request;
use Wiz\Core\Router;
use Wiz\Core\View;

$root = dirname(__DIR__);

/** @var \Wiz\Core\App $app */
$app = require $root . '/bootstrap/app.php';

// Templates call csrf_field() and old(); give the helpers the container.
$GLOBALS['__wiz_app'] = $app;

$config = $app->config('app');

date_default_timezone_set((string) ($config['timezone'] ?? 'Africa/Addis_Ababa'));
mb_internal_encoding('UTF-8');

if (($config['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

$router = new Router();

(require $root . '/routes/admin.php')($router, $app);
(require $root . '/routes/api.php')($router, $app);

$kernel = new Kernel(
    $app,
    $router,
    $app->get(View::class),
    $app->get(\Wiz\Core\Logger::class),
);

$kernel->handle(Request::capture())->send();
