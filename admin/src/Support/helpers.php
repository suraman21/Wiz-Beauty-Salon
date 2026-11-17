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

if (!function_exists('ethiopian_date')) {
    /**
     * The Ethiopian reading of a stored Gregorian date — "Hidar 8, 2019 EC".
     *
     * Every date the panel shows a person goes through here or through
     * full_date(), so the calendar is switched in one place. Storage and the
     * API stay Gregorian (see Wiz\Support\EthiopianDate).
     *
     * Styles: short · medium · long · full · amharic · month-year
     */
    function ethiopian_date(?string $value, string $style = 'long', string $fallback = '—'): string
    {
        return \Wiz\Support\EthiopianDate::format($value, $style, $fallback);
    }
}

if (!function_exists('ethiopian_clock')) {
    /**
     * "3:00 ሰዓት (09:00)" — the Ethiopian clock, with the 24-hour time the
     * salon sets and the database stores in brackets beside it.
     */
    function ethiopian_clock(?string $time, bool $withPeriod = false, string $fallback = '—'): string
    {
        if ($time === null || trim($time) === '') {
            return $fallback;
        }

        return \Wiz\Support\EthiopianDate::formatClock($time, $withPeriod);
    }
}

if (!function_exists('ethiopian_clock_label')) {
    /**
     * Just "3:00 ሰዓት" — for the narrow columns (booking rows, the dashboard
     * schedule) where the 24-hour cross-check would wrap onto four lines. Drop
     * it into a title attribute instead so it is still one hover away.
     */
    function ethiopian_clock_label(?string $time, string $fallback = '—'): string
    {
        if ($time === null || trim($time) === '') {
            return $fallback;
        }

        return \Wiz\Support\EthiopianDate::clock(substr($time, 0, 5))['label'] ?? $time;
    }
}

if (!function_exists('ethiopian_clock_range')) {
    /** "3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)" for opening hours. */
    function ethiopian_clock_range(?string $opensAt, ?string $closesAt, string $fallback = 'Closed'): string
    {
        if ($opensAt === null || $closesAt === null || trim($opensAt) === '' || trim($closesAt) === '') {
            return $fallback;
        }

        return \Wiz\Support\EthiopianDate::formatClockRange($opensAt, $closesAt);
    }
}

if (!function_exists('ethiopian_year')) {
    /** The Ethiopian year we are in now — 2019 for November 2026. */
    function ethiopian_year(): int
    {
        return \Wiz\Support\EthiopianDate::toEthiopian(date('Y-m-d'))['year'] ?? 0;
    }
}

if (!function_exists('full_date')) {
    /**
     * A timestamp as the panel shows it: Ethiopian date, then the Ethiopian
     * clock with the 24-hour time in brackets — "Hid 8, 2019 · 2:15 ሰዓት (11:15)".
     *
     * The Gregorian value is still what is stored and what the database holds;
     * this is a rendering choice, and it is deliberately made once, here, so
     * every list and detail screen changes together.
     */
    function full_date(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return $fallback;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $fallback;
        }

        return ethiopian_date($value, 'medium') . ' · ' . ethiopian_clock(date('H:i', $timestamp));
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
        if ($path === '/admin/categories' && isset($query['category']) && trim((string) $query['category']) !== '') {
            return '/admin/categories';
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
