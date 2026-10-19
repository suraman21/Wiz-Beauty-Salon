<?php
declare(strict_types=1);

use Wiz\Core\Csrf;
use Wiz\Core\Session;

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output. Every value that reaches a template goes
     * through here unless the template is deliberately emitting markup.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Absolute path for a route. The application is served from the domain
     * root, so this is a leading slash plus the path.
     */
    function url(string $path = '/'): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/admin/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $app = $GLOBALS['__wiz_app'] ?? null;

        if ($app instanceof \Wiz\Core\App) {
            return $app->get(Csrf::class)->field();
        }

        return '';
    }
}

if (!function_exists('old')) {
    /**
     * Previously submitted value for a field, after a failed submission.
     */
    function old(string $key, string $default = ''): string
    {
        $app = $GLOBALS['__wiz_app'] ?? null;

        if ($app instanceof \Wiz\Core\App) {
            return $app->get(Session::class)->old($key, $default);
        }

        return $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Read and clear a one-shot message.
     */
    function flash(string $key): ?string
    {
        $app = $GLOBALS['__wiz_app'] ?? null;

        if ($app instanceof \Wiz\Core\App) {
            return $app->get(Session::class)->flash($key);
        }

        return null;
    }
}

if (!function_exists('current_user')) {
    /**
     * @return array<string, mixed>|null
     */
    function current_user(): ?array
    {
        $app = $GLOBALS['__wiz_app'] ?? null;

        if ($app instanceof \Wiz\Core\App) {
            return $app->get(\Wiz\Core\Auth::class)->user();
        }

        return null;
    }
}

if (!function_exists('activity_label')) {
    /**
     * Turn an audit action key into words a staff member can read.
     */
    function activity_label(string $action): string
    {
        return match ($action) {
            'auth.login' => 'Signed in',
            'auth.logout' => 'Signed out',
            'auth.password_changed' => 'Changed their password',
            default => ucfirst(str_replace(['.', '_'], ' ', $action)),
        };
    }
}

if (!function_exists('full_date')) {
    function full_date(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $fallback : date('j M Y, H:i', $timestamp);
    }
}
