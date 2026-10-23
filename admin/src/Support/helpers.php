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
        $relative = ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public/assets/' . $relative;
        $version = is_file($file) ? (string) filemtime($file) : '';

        return '/admin/assets/' . $relative . ($version === '' ? '' : '?v=' . $version);
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
            'team.created' => 'Added a team member',
            'team.updated' => 'Updated a team member',
            'team.activated' => 'Switched on an account',
            'team.deactivated' => 'Switched off an account',
            'settings.mail_updated' => 'Changed email settings',
            'settings.test_email' => 'Sent a test email',
            'auth.logout' => 'Signed out',
            'auth.password_changed' => 'Changed their password',
            'account.profile_updated' => 'Updated their account details',
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

if (!function_exists('admin_back_href')) {
    /**
     * Universal back link for the admin panel.
     *
     * Controllers may pass an explicit $explicit href (e.g. category-aware
     * back). When none is given, the helper infers a parent from the
     * current URL — path segments and known query states — so every
     * detail/edit screen automatically has a way back.
     */
    function admin_back_href(?string $explicit = null): ?string
    {
        if ($explicit !== null && trim($explicit) !== '') {
            return $explicit;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim(rawurldecode($path), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        $query = $_GET;

        // Query-driven detail states (path stays on the list route)
        if ($path === '/admin/services' && isset($query['category']) && trim((string) $query['category']) !== '') {
            return '/admin/services';
        }
        if ($path === '/admin/email-templates' && isset($query['type']) && trim((string) $query['type']) !== '') {
            return '/admin/email-templates';
        }
        if ($path === '/admin/categories' && isset($query['edit']) && trim((string) $query['edit']) !== '') {
            return '/admin/categories';
        }

        // Path-driven detail states: /admin/{section}/{id} or /admin/{section}/{id}/edit
        $parts = explode('/', trim($path, '/')); // e.g. ['admin','bookings','123']
        if (count($parts) >= 3 && $parts[0] === 'admin' && $parts[1] !== '') {
            // Top-level list pages are 2 parts (admin + section) — no back
            // Anything deeper is a detail/edit/new screen — back to its section
            return '/admin/' . $parts[1];
        }

        return null;
    }
}
