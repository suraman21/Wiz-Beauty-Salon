<?php
declare(strict_types=1);

namespace Wiz\Support;

use Wiz\Core\HttpException;
use Wiz\Core\Request;

/**
 * The only cross-site check the public JSON endpoints need.
 *
 * A browser always sends Origin on a cross-site POST. If it names another
 * site, the request did not come from the salon's own pages, whatever the body
 * says. Requests without an Origin (curl, server-to-server) are allowed: the
 * public endpoints carry no session cookie, so there is nothing to ride.
 */
final class Origin
{
    public static function assertSame(Request $request, string $appOrigin): void
    {
        $origin = $request->header('Origin');

        if ($origin === null || $origin === '' || $appOrigin === '') {
            return;
        }

        $allowed = parse_url($appOrigin, PHP_URL_HOST);
        $given = parse_url($origin, PHP_URL_HOST);

        if (!is_string($allowed) || !is_string($given) || strcasecmp($given, $allowed) !== 0) {
            throw HttpException::forbidden('Requests must come from the salon website.');
        }
    }
}
