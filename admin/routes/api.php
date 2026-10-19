<?php
declare(strict_types=1);

/**
 * JSON API consumed by the React site.
 *
 * Public read endpoints land here from Phase 2 onward. Nothing is exposed yet,
 * so a request returns a clean 404 rather than an HTML error page.
 */

use Wiz\Core\App;
use Wiz\Core\Router;

return static function (Router $router, App $app): void {
    // Reserved for Phase 2: /api/v1/services, /gallery, /reviews, /settings
};
