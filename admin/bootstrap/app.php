<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Builds the service container, registers every service as a singleton and
 * returns the configured App instance. Nothing in here touches the database
 * until something asks for it — the CLI, tests and error pages can run without
 * a live connection.
 *
 * Every binding is written out explicitly. There is no autowiring: a class that
 * is not registered here cannot be resolved, which makes the dependency graph
 * readable in one screen.
 */

use Wiz\Controllers\Admin\AuthController;
use Wiz\Controllers\Admin\DashboardController;
use Wiz\Core\App;
use Wiz\Core\Auth;
use Wiz\Core\Config;
use Wiz\Core\Csrf;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Core\Logger;
use Wiz\Core\Middleware\AdminOnlyMiddleware;
use Wiz\Core\Middleware\AuthMiddleware;
use Wiz\Core\Middleware\CsrfMiddleware;
use Wiz\Core\Middleware\GuestMiddleware;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\LoginAttemptRepository;
use Wiz\Repositories\UserRepository;
use Wiz\Services\AuthService;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$app = new App($root);

// ------------------------------------------------------------------- core
$app->singleton(Config::class, static fn (App $a): Config => new Config($a->basePath('config')));

$app->singleton(Logger::class, static fn (App $a): Logger => new Logger($a->basePath('storage/logs')));

$app->singleton(Database::class, static fn (App $a): Database => new Database(
    $a->config('database') ?? [],
));

$app->singleton(Session::class, static fn (App $a): Session => new Session($a->config('app') ?? []));

$app->singleton(Csrf::class, static fn (App $a): Csrf => new Csrf($a->get(Session::class)));

$app->singleton(View::class, static fn (App $a): View => new View($a->basePath('src/Views')));

// ----------------------------------------------------------- data access
$app->singleton(UserRepository::class, static fn (App $a): UserRepository => new UserRepository(
    $a->get(Database::class),
));

$app->singleton(LoginAttemptRepository::class, static fn (App $a): LoginAttemptRepository => new LoginAttemptRepository(
    $a->get(Database::class),
));

$app->singleton(ActivityLogRepository::class, static fn (App $a): ActivityLogRepository => new ActivityLogRepository(
    $a->get(Database::class),
));

// -------------------------------------------------------------- services
$app->singleton(Auth::class, static fn (App $a): Auth => new Auth(
    $a->get(Session::class),
    $a->get(UserRepository::class),
));

$app->singleton(AuthService::class, static fn (App $a): AuthService => new AuthService(
    $a->get(UserRepository::class),
    $a->get(LoginAttemptRepository::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Auth::class),
    $a->get(Logger::class),
    (int) ($a->config('app.login_max_attempts') ?? 5),
    (int) ($a->config('app.login_lockout_minutes') ?? 15),
));

// ----------------------------------------------------------- middleware
$app->singleton(AuthMiddleware::class, static fn (App $a): AuthMiddleware => new AuthMiddleware(
    $a->get(Auth::class),
));

$app->singleton(GuestMiddleware::class, static fn (App $a): GuestMiddleware => new GuestMiddleware(
    $a->get(Auth::class),
));

$app->singleton(CsrfMiddleware::class, static fn (App $a): CsrfMiddleware => new CsrfMiddleware(
    $a->get(Csrf::class),
));

$app->singleton(AdminOnlyMiddleware::class, static fn (App $a): AdminOnlyMiddleware => new AdminOnlyMiddleware(
    $a->get(Auth::class),
));

// ---------------------------------------------------------- controllers
$app->singleton(AuthController::class, static fn (App $a): AuthController => new AuthController(
    $a->get(View::class),
    $a->get(Session::class),
    $a->get(Csrf::class),
    $a->get(AuthService::class),
));

$app->singleton(DashboardController::class, static fn (App $a): DashboardController => new DashboardController(
    $a->get(View::class),
    $a->get(Auth::class),
    $a->get(Database::class),
    $a->get(ActivityLogRepository::class),
    $a->get(UserRepository::class),
));

return $app;
