<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Immutable snapshot of the incoming request.
 *
 * Everything the application needs from the outside world is read once, here,
 * so controllers receive plain values instead of superglobals.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $server = [],
        private readonly array $files = [],
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        return new self(
            $method,
            self::normalisePath($path),
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
        );
    }

    public static function normalisePath(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');

        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Read from the request body, falling back to the query string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function ip(): string
    {
        // Cloudflare terminates TLS for this origin and sets the real client IP.
        $candidates = [
            $this->header('CF-Connecting-IP'),
            $this->header('X-Forwarded-For'),
            $this->server['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            // X-Forwarded-For may be a list: take the left-most entry.
            $ip = trim(explode(',', $candidate)[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->header('User-Agent') ?? ''), 0, 255);
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept'));

        return str_contains($accept, 'application/json') || str_starts_with($this->path, '/api/');
    }

    /**
     * @return array<string, mixed>
     */
    public function file(string $key): array
    {
        $file = $this->files[$key] ?? [];

        /** @var array<string, mixed> */
        return is_array($file) ? $file : [];
    }
}
