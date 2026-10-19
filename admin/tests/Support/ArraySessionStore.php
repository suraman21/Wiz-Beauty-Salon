<?php
declare(strict_types=1);

namespace Wiz\Tests\Support;

use Wiz\Core\Contracts\SessionStore;

/**
 * In-memory session for unit tests.
 *
 * Lets the security services be exercised without starting a real PHP session,
 * which would need headers, a writable save path and a fresh process per test.
 */
final class ArraySessionStore implements SessionStore
{
    /** @var array<string, mixed> */
    private array $data = [];

    public int $regenerations = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }
}
