<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Signals that the request should be redirected immediately, usually from
 * middleware or a controller shortcut such as a failed login.
 */
final class RedirectException extends \RuntimeException
{
    public function __construct(
        private readonly string $location,
        private readonly int $status = 302,
    ) {
        parent::__construct('Redirecting to ' . $location);
    }

    public function location(): string
    {
        return $this->location;
    }

    public function status(): int
    {
        return $this->status;
    }
}
