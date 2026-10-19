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
     * @param list<string> $trustedProxies addresses or CIDR ranges allowed to
     *                                     set the client-IP headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $server = [],
        private readonly array $files = [],
        private readonly array $trustedProxies = [],
    ) {
    }

    /**
     * @param list<string> $trustedProxies
     */
    public static function capture(array $trustedProxies = []): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        return new self(
            $method,
            self::normalisePath($path),
            $_GET,
            self::body(),
            $_SERVER,
            $_FILES,
            $trustedProxies,
        );
    }

    /**
     * Form fields, or the decoded JSON body when the client sent JSON.
     *
     * @return array<string, mixed>
     */
    private static function body(): array
    {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (!str_contains($type, 'application/json')) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        /** @var array<string, mixed> $decoded */
        return is_array($decoded) ? $decoded : [];
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
        $remote = trim((string) ($this->server['REMOTE_ADDR'] ?? ''));

        // Forwarding headers are only believed when the request actually came
        // from the proxy that sets them. Anyone can send CF-Connecting-IP or
        // X-Forwarded-For, so trusting them unconditionally would hand every
        // rate limit and login lockout to the caller.
        if ($remote === '' || !$this->isTrustedProxy($remote)) {
            return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
        }

        $candidates = [
            $this->header('CF-Connecting-IP'),
            $this->header('X-Forwarded-For'),
            $remote,
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

    /**
     * Can this request tell one visitor from another?
     *
     * True for a direct request, and for one that arrived through a proxy we
     * trust. False when a proxy is in front but its range is not configured —
     * in that case every visitor shares one address, so per-IP throttling
     * would punish the whole site instead of one abuser.
     */
    public function trustsClientIp(): bool
    {
        $remote = trim((string) ($this->server['REMOTE_ADDR'] ?? ''));

        if ($remote === '') {
            return false;
        }

        if ($this->isTrustedProxy($remote)) {
            return true;
        }

        return $this->header('CF-Connecting-IP') === null
            && $this->header('X-Forwarded-For') === null;
    }

    private function isTrustedProxy(string $ip): bool
    {
        foreach ($this->trustedProxies as $range) {
            $range = trim($range);

            if ($range !== '' && self::ipMatches($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does an address fall inside "203.0.113.7" or "203.0.113.0/24"?
     */
    private static function ipMatches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        $ipBinary = @inet_pton($ip);
        $netBinary = @inet_pton(trim($subnet));

        if ($ipBinary === false || $netBinary === false || strlen($ipBinary) !== strlen($netBinary)) {
            return false;
        }

        $maxBits = strlen($ipBinary) * 8;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);

        if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($netBinary, 0, $wholeBytes)) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($netBinary[$wholeBytes]) & $mask);
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
