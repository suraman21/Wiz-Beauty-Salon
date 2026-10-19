<?php
declare(strict_types=1);

namespace Wiz\Core\Middleware;

use Wiz\Core\Csrf;
use Wiz\Core\HttpException;
use Wiz\Core\Request;

/**
 * Rejects any state-changing request that does not carry the session token.
 *
 * Applied to every POST route by the dispatcher rather than route by route, so
 * a new form cannot accidentally ship without it.
 */
final class CsrfMiddleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request): void
    {
        if (!$request->isPost()) {
            return;
        }

        if ($this->csrf->verify($request)) {
            return;
        }

        throw new HttpException(419);
    }
}
