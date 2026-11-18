<?php
/**
 * Front controller shim.
 *
 * Lives in the public docroot; the application itself is one level up, outside
 * the webroot. Deployed automatically — do not edit on the server.
 */
declare(strict_types=1);

$app = dirname(__DIR__, 1) . '/wiz-app/public/index.php';
$alt = '/home/arkeonet/wiz-app/public/index.php';

require is_file($app) ? $app : $alt;
