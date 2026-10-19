<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Minimal service container.
 *
 * Deliberately small: bindings are closures resolved once and cached. No
 * autowiring, no interface scanning — every dependency is explicit, which is
 * what keeps a plain-PHP application readable.
 */
final class App
{
    /** @var array<class-string, \Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<class-string, mixed> */
    private array $instances = [];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param class-string $id
     * @param \Closure(self): mixed $factory
     */
    public function singleton(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException(sprintf('Service not bound: %s', $id));
        }

        $instance = ($this->bindings[$id])($this);
        $this->instances[$id] = $instance;

        /** @var T */
        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Read a configuration value with dot notation.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->get(Config::class)->get($key, $default);
    }
}
