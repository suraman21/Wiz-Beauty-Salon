<?php
declare(strict_types=1);

/**
 * Admin routes.
 *
 * Everything here is server-rendered and session-authenticated. Routes marked
 * `admin` require the administrator role in addition to being signed in.
 */

use Wiz\Controllers\Admin\AccountController;
use Wiz\Controllers\Admin\ActivityController;
use Wiz\Controllers\Admin\AuthController;
use Wiz\Controllers\PublicBookingController;
use Wiz\Controllers\Admin\BookingController;
use Wiz\Controllers\Admin\CategoryController;
use Wiz\Controllers\Admin\DashboardController;
use Wiz\Controllers\Admin\EmailTemplateController;
use Wiz\Controllers\Admin\GalleryController;
use Wiz\Controllers\Admin\WebsiteImagesController;
use Wiz\Controllers\Admin\MessageController;
use Wiz\Controllers\Admin\ReviewController;
use Wiz\Controllers\Admin\ScheduleController;
use Wiz\Controllers\Admin\SettingsController;
use Wiz\Controllers\Admin\TermsController;
use Wiz\Controllers\Admin\UserController;
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
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!isset($types[$extension])) {
            return Response::html('Not found', 404);
        }

        return Response::file($app->basePath('public/assets/' . $file), $types[$extension]);
    });

    // --------------------------------------------------------------- uploads
    // In production the web server serves these files directly because they sit
    // in the docroot; this route is what makes the same paths work when PHP
    // handles every request (local development, or a misconfigured docroot).
    $router->get('/uploads/{file}', static function (\Wiz\Core\Request $request, array $params) use ($app): Response {
        $name = basename((string) ($params['file'] ?? ''));

        // Only names this application generates: 24 hex characters + extension.
        if (preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name) !== 1) {
            return Response::html('Not found', 404);
        }

        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        $directory = (string) ($app->config('app.upload_path') ?? $app->basePath('storage/uploads'));

        return Response::file(rtrim($directory, '/') . '/' . $name, $types[$extension]);
    });

    // ------------------------------------------------------------------ auth
    $router->get('/admin/login', [AuthController::class, 'showLogin'], ['guest']);
    $router->post('/admin/login', [AuthController::class, 'login'], ['guest']);
    $router->post('/admin/logout', [AuthController::class, 'logout'], ['auth']);

    // ------------------------------------------- the customer's own booking
    // Reached from the link in the confirmation email. The signed token is the
    // credential, so these live outside the admin session on purpose.
    $router->get('/booking/manage/{token}', [PublicBookingController::class, 'show']);
    $router->post('/booking/manage/{token}/cancel', [PublicBookingController::class, 'cancel']);
    $router->post('/booking/manage/{token}/reschedule', [PublicBookingController::class, 'reschedule']);

    // ------------------------------------------------------------- dashboard
    $router->get('/admin', [DashboardController::class, 'index'], ['auth']);

    // ------------------------------------------------------------- my account
    // Available to every signed-in person, staff included: changing your own
    // password is not an administrative act.
    $router->get('/admin/account', [AccountController::class, 'index'], ['auth']);
    $router->post('/admin/account/profile', [AccountController::class, 'updateProfile'], ['auth']);
    $router->post('/admin/account/password', [AccountController::class, 'updatePassword'], ['auth']);

    // -------------------------------------------------------------- bookings
    $router->get('/admin/bookings', [BookingController::class, 'index'], ['auth']);
    $router->get('/admin/bookings/new', [BookingController::class, 'create'], ['auth']);
    $router->post('/admin/bookings', [BookingController::class, 'store'], ['auth']);
    $router->get('/admin/bookings/{id}', [BookingController::class, 'show'], ['auth']);
    $router->post('/admin/bookings/{id}/status', [BookingController::class, 'transition'], ['auth']);
    $router->post('/admin/bookings/{id}/reschedule', [BookingController::class, 'reschedule'], ['auth']);
    $router->post('/admin/bookings/{id}/notes', [BookingController::class, 'notes'], ['auth']);

    // -------------------------------------------------------------- messages
    $router->get('/admin/messages', [MessageController::class, 'index'], ['auth']);
    $router->post('/admin/messages/read-all', [MessageController::class, 'readAll'], ['auth']);
    $router->post('/admin/messages/{id}/toggle', [MessageController::class, 'toggle'], ['auth']);
    $router->post('/admin/messages/{id}/delete', [MessageController::class, 'destroy'], ['auth']);

    // -------------------------------------------------------------- schedule
    $router->get('/admin/schedule', [ScheduleController::class, 'index'], ['auth']);
    $router->post('/admin/schedule/hours', [ScheduleController::class, 'saveHours'], ['auth']);
    $router->post('/admin/schedule/closures', [ScheduleController::class, 'storeClosure'], ['auth']);
    $router->post('/admin/schedule/closures/{id}/delete', [ScheduleController::class, 'destroyClosure'], ['auth']);

    // -------------------------------------------------------------- services
    // The services are the categories: one row per thing the salon offers —
    // Hair, Nails, Makeup, Skin — edited on that screen.

    // ------------------------------------------------------------ categories
    $router->get('/admin/categories', [CategoryController::class, 'index'], ['auth']);
    $router->post('/admin/categories', [CategoryController::class, 'store'], ['auth']);
    $router->post('/admin/categories/{id}', [CategoryController::class, 'update'], ['auth']);
    $router->post('/admin/categories/{id}/delete', [CategoryController::class, 'destroy'], ['auth']);
    $router->post('/admin/categories/{id}/move', [CategoryController::class, 'move'], ['auth']);

    // --------------------------------------------------------------- gallery
    $router->get('/admin/gallery', [GalleryController::class, 'index'], ['auth']);
    $router->post('/admin/gallery', [GalleryController::class, 'store'], ['auth']);
    $router->post('/admin/gallery/{id}/toggle', [GalleryController::class, 'toggle'], ['auth']);
    $router->post('/admin/gallery/{id}/home', [GalleryController::class, 'home'], ['auth']);
    $router->post('/admin/gallery/{id}/delete', [GalleryController::class, 'destroy'], ['auth']);
    $router->post('/admin/gallery/{id}/move', [GalleryController::class, 'move'], ['auth']);
    $router->post('/admin/gallery/{id}/category', [GalleryController::class, 'setCategory'], ['auth']);

    // --------------------------------------------------------- website images
    $router->get('/admin/images', [WebsiteImagesController::class, 'index'], ['auth']);
    $router->post('/admin/images/{id}', [WebsiteImagesController::class, 'update'], ['auth']);

    // ----------------------------------------------------------------- team
    // Administrators only: these screens decide who else may sign in.
    $router->get('/admin/users', [UserController::class, 'index'], ['auth', 'admin']);
    $router->get('/admin/users/new', [UserController::class, 'create'], ['auth', 'admin']);
    $router->post('/admin/users', [UserController::class, 'store'], ['auth', 'admin']);
    $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'admin']);
    $router->post('/admin/users/{id}', [UserController::class, 'update'], ['auth', 'admin']);
    $router->post('/admin/users/{id}/activate', [UserController::class, 'activate'], ['auth', 'admin']);
    $router->post('/admin/users/{id}/deactivate', [UserController::class, 'deactivate'], ['auth', 'admin']);

    // --------------------------------------------------------------- audit
    $router->get('/admin/activity', [ActivityController::class, 'index'], ['auth', 'admin']);

    // --------------------------------------------------------- email designs
    // Administrator-only, draft-first; these routes never send an email.
    $router->get('/admin/email-templates', [EmailTemplateController::class, 'index'], ['auth', 'admin']);
    $router->post('/admin/email-templates/save', [EmailTemplateController::class, 'save'], ['auth', 'admin']);
    $router->post('/admin/email-templates/publish', [EmailTemplateController::class, 'publish'], ['auth', 'admin']);

    // ---------------------------------------------------- booking terms
    // The page a customer agrees to. One screen: every section, every clause,
    // both languages. Placed beside Settings because it is content the salon
    // owns, not a catalogue to manage.
    $router->get('/admin/terms', [TermsController::class, 'index'], ['auth', 'admin']);
    $router->post('/admin/terms/sections', [TermsController::class, 'storeSection'], ['auth', 'admin']);
    $router->post('/admin/terms/sections/{id}', [TermsController::class, 'updateSection'], ['auth', 'admin']);
    $router->post('/admin/terms/sections/{id}/delete', [TermsController::class, 'destroySection'], ['auth', 'admin']);
    $router->post('/admin/terms/sections/{id}/move', [TermsController::class, 'moveSection'], ['auth', 'admin']);
    $router->post('/admin/terms/clauses', [TermsController::class, 'storeClause'], ['auth', 'admin']);
    $router->post('/admin/terms/clauses/{id}', [TermsController::class, 'updateClause'], ['auth', 'admin']);
    $router->post('/admin/terms/clauses/{id}/delete', [TermsController::class, 'destroyClause'], ['auth', 'admin']);
    $router->post('/admin/terms/clauses/{id}/move', [TermsController::class, 'moveClause'], ['auth', 'admin']);

    // ------------------------------------------------------------- settings
    $router->get('/admin/settings', [SettingsController::class, 'index'], ['auth', 'admin']);
    $router->post('/admin/settings/mail', [SettingsController::class, 'saveMail'], ['auth', 'admin']);
    $router->post('/admin/settings/test', [SettingsController::class, 'sendTest'], ['auth', 'admin']);
    $router->post('/admin/settings/salon', [SettingsController::class, 'saveSalon'], ['auth', 'admin']);
    $router->post('/admin/settings/booking', [SettingsController::class, 'saveBooking'], ['auth', 'admin']);

    // --------------------------------------------------------------- reviews
    $router->get('/admin/reviews', [ReviewController::class, 'index'], ['auth']);
    $router->post('/admin/reviews', [ReviewController::class, 'store'], ['auth']);
    $router->post('/admin/reviews/{id}/approve', [ReviewController::class, 'approve'], ['auth']);
    $router->post('/admin/reviews/{id}/delete', [ReviewController::class, 'destroy'], ['auth']);
};
