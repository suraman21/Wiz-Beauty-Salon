<?php
declare(strict_types=1);

namespace Wiz\Core;

use Wiz\Core\Contracts\SessionStore;

/**
 * Synchroniser-token CSRF protection.
 *
 * A single token per session, compared with hash_equals. POST requests that
 * fail verification are rejected before the controller runs.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD = '_token';
    private const HEADER = 'X-CSRF-Token';

    public function __construct(private readonly SessionStore $session)
    {
    }

    public function token(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }

    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            e($this->token()),
        );
    }

    public function verify(Request $request): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $provided = $request->input(self::FIELD)
            ?? $request->header(self::HEADER);

        if (!is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function rotate(): void
    {
        $this->session->put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
