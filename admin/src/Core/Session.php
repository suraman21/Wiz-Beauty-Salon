<?php
declare(strict_types=1);

namespace Wiz\Core;

use Wiz\Core\Contracts\SessionStore;

/**
 * Session handling with hardened cookie defaults.
 *
 * The cookie is HttpOnly, SameSite=Lax and Secure in production. The ID is
 * regenerated on privilege changes (login/logout), which is what prevents
 * session fixation.
 */
final class Session implements SessionStore
{
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $secure = (bool) ($this->config['session_secure'] ?? true);

        session_name((string) ($this->config['session_name'] ?? 'wiz_admin'));

        session_set_cookie_params([
            'lifetime' => (int) ($this->config['session_lifetime'] ?? 7200),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $savePath = $this->config['session_path'] ?? null;

        if (is_string($savePath) && $savePath !== '' && is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        $this->start();

        return isset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies') === '1') {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * Store a one-shot message, read once and removed.
     */
    public function flash(string $key, ?string $value = null): ?string
    {
        $this->start();

        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;

            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }

    /**
     * Remember submitted values so a failed form can be re-populated.
     *
     * @param array<string, mixed> $input
     */
    public function flashInput(array $input): void
    {
        $safe = [];

        foreach ($input as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $safe[(string) $key] = (string) $value;
            }
        }

        unset($safe['password'], $safe['_token'], $safe['password_confirmation']);

        $this->put('_old_input', $safe);
    }

    public function old(string $key, string $default = ''): string
    {
        $input = $this->get('_old_input', []);

        if (!is_array($input)) {
            return $default;
        }

        $value = $input[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function clearOld(): void
    {
        $this->forget('_old_input');
    }
}
