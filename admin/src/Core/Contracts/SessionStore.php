<?php
declare(strict_types=1);

namespace Wiz\Core\Contracts;

/**
 * The slice of the session the security services depend on.
 *
 * Keeping this narrow means CSRF and authentication can be tested without a
 * running PHP session, and neither of them can reach into unrelated session
 * state.
 */
interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function forget(string $key): void;

    public function has(string $key): bool;

    public function regenerate(): void;
}
