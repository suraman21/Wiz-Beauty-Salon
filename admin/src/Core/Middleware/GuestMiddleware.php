<?php
declare(strict_types=1);

namespace Wiz\Core\Middleware;

use Wiz\Core\Auth;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;

/**
 * Keeps signed-in users off the login screen.
 */
final class GuestMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request): void
    {
        if ($this->auth->check()) {
            throw new RedirectException('/admin');
        }
    }
}
