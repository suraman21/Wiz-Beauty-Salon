<?php
declare(strict_types=1);

namespace Wiz\Core\Middleware;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\Request;

/**
 * Restricts a route to the `admin` role. Staff accounts are blocked at the
 * routing layer and the check is repeated inside the service that performs the
 * privileged action.
 */
final class AdminOnlyMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request): void
    {
        if ($this->auth->isAdmin()) {
            return;
        }

        throw HttpException::forbidden();
    }
}
