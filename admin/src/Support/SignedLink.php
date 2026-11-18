<?php
declare(strict_types=1);

namespace Wiz\Support;

use RuntimeException;

/**
 * Links that carry their own permission.
 *
 * A customer who books online needs to be able to cancel or move that booking
 * without an account. The link we email them is the credential: it names the
 * booking and an expiry, and it is signed with APP_KEY, so the salon's server
 * can accept it later without storing per-booking secrets.
 *
 * Nothing secret is in the link — only "this is booking 42, valid until then".
 * Change one character and the signature fails.
 */
final class SignedLink
{
    public function __construct(private readonly string $key)
    {
        if (strlen($this->key) < 32) {
            throw new RuntimeException(
                'APP_KEY is missing or too short. Set a 32+ character APP_KEY in .env.',
            );
        }
    }

    /**
     * @param int $ttlSeconds how long the link stays usable
     */
    public function issue(string $purpose, int $id, int $ttlSeconds = 86400): string
    {
        $payload = $purpose . ':' . $id . ':' . (time() + max(1, $ttlSeconds));

        return $this->encode($payload) . '.' . $this->encode($this->sign($payload));
    }

    /**
     * @return array{purpose: string, id: int}|null null when the token is
     *         malformed, forged or expired — the caller cannot tell which, and
     *         does not need to.
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        $payload = $this->decode($parts[0]);
        $mac = $this->decode($parts[1]);

        if ($payload === null || $mac === null) {
            return null;
        }

        if (!hash_equals($this->sign($payload), $mac)) {
            return null;
        }

        $fields = explode(':', $payload);

        if (count($fields) !== 3) {
            return null;
        }

        [$purpose, $id, $expires] = $fields;

        if (!ctype_digit($id) || !ctype_digit($expires)) {
            return null;
        }

        if ((int) $expires < time()) {
            return null;
        }

        return ['purpose' => $purpose, 'id' => (int) $id];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->key, true);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
