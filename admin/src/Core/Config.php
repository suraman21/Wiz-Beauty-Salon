<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Configuration accessor.
 *
 * Each file in config/ returns a plain array and is loaded lazily. Values are
 * addressed with dot notation: `app.name`, `database.host`.
 */
final class Config
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(private readonly string $configPath)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if ($file === null || $file === '') {
            return $default;
        }

        $values = $this->file($file);

        foreach ($segments as $segment) {
            if (!is_array($values) || !array_key_exists($segment, $values)) {
                return $default;
            }

            $values = $values[$segment];
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function file(string $name): array
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $path = $this->configPath . '/' . $name . '.php';

        if (!is_file($path)) {
            return $this->loaded[$name] = [];
        }

        /** @var array<string, mixed> $values */
        $values = require $path;

        return $this->loaded[$name] = $values;
    }
}
