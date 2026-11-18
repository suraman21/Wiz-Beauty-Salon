<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Outgoing response.
 *
 * Controllers return one of these instead of echoing, which keeps output in a
 * single place and makes headers, status codes and redirects testable.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadBody = $body === false ? '{"error":"encoding"}' : $body;

        return new self($payloadBody, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Serve a file from disk with a long cache lifetime. Used for admin assets,
     * which have no CDN path of their own because the docroot belongs to the
     * React build.
     */
    public static function file(string $path, string $contentType, int $maxAge = 86400): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::html('Not found', 404);
        }

        $contents = file_get_contents($path);

        return new self($contents === false ? '' : $contents, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=' . $maxAge,
            'Last-Modified' => gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT',
        ]);
    }

    public function withStatus(int $status): self
    {
        return new self($this->body, $status, $this->headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->body, $this->status, $headers);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
