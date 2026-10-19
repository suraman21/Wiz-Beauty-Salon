<?php
declare(strict_types=1);

/**
 * Admin routes.
 *
 * Everything here is server-rendered and session-authenticated. Routes marked
 * `admin` require the administrator role in addition to being signed in.
 */

use Wiz\Controllers\Admin\AuthController;
use Wiz\Controllers\Admin\DashboardController;
use Wiz\Core\App;
use Wiz\Core\Router;
use Wiz\Core\Response;

return static function (Router $router, App $app): void {
    // ---------------------------------------------------------------- assets
    $router->get('/admin/assets/{file}', static function (\Wiz\Core\Request $request, array $params) use ($app): Response {
        $file = basename((string) ($params['file'] ?? ''));

        $types = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'ico' => 'image/x-icon',
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!isset($types[$extension])) {
            return Response::html('Not found', 404);
        }

        return Response::file($app->basePath('public/assets/' . $file), $types[$extension]);
    });

    // ------------------------------------------------------------------ auth
    $router->get('/admin/login', [AuthController::class, 'showLogin'], ['guest']);
    $router->post('/admin/login', [AuthController::class, 'login'], ['guest']);
    $router->post('/admin/logout', [AuthController::class, 'logout'], ['auth']);

    // ------------------------------------------------------------- dashboard
    $router->get('/admin', [DashboardController::class, 'index'], ['auth']);
};
