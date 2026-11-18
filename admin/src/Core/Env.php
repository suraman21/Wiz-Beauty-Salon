<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Reads a .env file into $_ENV / $_SERVER.
 *
 * Supports `KEY=value`, quoted values, inline `#` comments and `export`
 * prefixes. Values are never interpolated, so a password containing `$` or
 * quotes is stored exactly as written.
 */
final class Env
{
    public static function load(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $position = strpos($line, '=');

            if ($position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            $value = self::parseValue(trim(substr($line, $position + 1)));

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first === '"' || $first === "'") {
            $last = strrpos($value, $first);

            if ($last !== false && $last > 0) {
                return substr($value, 1, $last - 1);
            }
        }

        // Strip an unquoted trailing comment: "value # note"
        $hash = strpos($value, ' #');

        if ($hash !== false) {
            $value = substr($value, 0, $hash);
        }

        return trim($value);
    }
}
