<?php
declare(strict_types=1);

/**
 * JSON API consumed by the React site.
 *
 * Only seven endpoints are public: the service catalogue, the times a service is
 * available on one date, the same answer for a run of days, and asking for a
 * booking. Everything else stays
 * behind the admin session.
 *
 * These paths carry no session cookie, so the dispatcher does not apply CSRF
 * here; the write endpoint checks the Origin header instead, and both are
 * validated on the server exactly like the admin forms.
 */

use Wiz\Controllers\Api\BookingApiController;
use Wiz\Controllers\Api\ContentApiController;
use Wiz\Controllers\Api\MessageApiController;
use Wiz\Core\App;
use Wiz\Core\Router;

return static function (Router $router, App $app): void {
    $router->get('/api/v1/services', [BookingApiController::class, 'services']);
    $router->get('/api/v1/gallery', [ContentApiController::class, 'gallery']);
    $router->get('/api/v1/reviews', [ContentApiController::class, 'reviews']);
    $router->get('/api/v1/salon', [ContentApiController::class, 'salon']);
    $router->get('/api/v1/images', [ContentApiController::class, 'images']);
    $router->get('/api/v1/availability', [BookingApiController::class, 'availability']);
    $router->get('/api/v1/calendar', [BookingApiController::class, 'calendar']);
    $router->post('/api/v1/bookings', [BookingApiController::class, 'store']);
    $router->post('/api/v1/bookings/recover', [BookingApiController::class, 'recover']);
    $router->post('/api/v1/messages', [MessageApiController::class, 'store']);
};
