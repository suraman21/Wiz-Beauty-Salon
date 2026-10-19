<?php
declare(strict_types=1);

use Wiz\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'WIZ Beauty Salon'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', 'https://wiz.arkeonethiopia.com') ?? '', '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Africa/Addis_Ababa'),
    'key' => Env::get('APP_KEY', ''),

    'session_name' => Env::get('SESSION_NAME', 'wiz_admin'),
    'session_lifetime' => Env::int('SESSION_LIFETIME', 7200),
    'session_secure' => Env::bool('SESSION_SECURE', true),
    'session_path' => dirname(__DIR__) . '/storage/sessions',

    // Where uploaded images are written. In production this is the docroot's
    // uploads/ directory so the web server serves them directly; the PHP app
    // itself stays outside the webroot.
    'upload_path' => Env::get('UPLOAD_PATH', dirname(__DIR__) . '/storage/uploads'),
    'upload_url' => Env::get('UPLOAD_URL', '/uploads'),
    'upload_max_bytes' => Env::int('UPLOAD_MAX_BYTES', 5 * 1024 * 1024),

    // Comma-separated addresses or CIDR ranges of reverse proxies whose
    // forwarding headers may be believed (Cloudflare's ranges in production).
    // Empty means "trust REMOTE_ADDR only", which is the safe default.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) Env::get('TRUSTED_PROXIES', '')),
    ))),

    // Failed logins allowed per email/IP inside the window.
    // Five failures for one email from one address; a wider per-address limit
    // so one machine cannot work through a list of accounts.
    'login_max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
    'login_max_attempts_per_ip' => Env::int('LOGIN_MAX_ATTEMPTS_PER_IP', 25),
    'login_lockout_minutes' => Env::int('LOGIN_LOCKOUT_MINUTES', 15),
];
