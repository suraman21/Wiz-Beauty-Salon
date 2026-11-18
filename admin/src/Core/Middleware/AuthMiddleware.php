<?php
declare(strict_types=1);

namespace Wiz\Core\Middleware;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\Request;

/**
 * Requires an authenticated user. Unauthenticated visitors are sent to the
 * login page, preserving where they were trying to go.
 */
final class AuthMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request): void
    {
        if ($this->auth->check()) {
            return;
        }

        if ($request->wantsJson()) {
            throw HttpException::unauthorized();
        }

        $target = $request->path();

        throw new \Wiz\Core\RedirectException(
            '/admin/login?next=' . rawurlencode($target),
        );
    }
}
