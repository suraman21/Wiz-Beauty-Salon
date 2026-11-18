<?php
declare(strict_types=1);

use Wiz\Core\Env;

// Forwarding headers are only believed from these addresses. When
// TRUSTED_PROXIES is empty the list defaults to Cloudflare's published ranges,
// because the site is served through Cloudflare: without them every visitor
// would share one edge address, which would make rate limits and the audit
// trail meaningless. A request from anywhere else cannot set its own IP.
$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) Env::get('TRUSTED_PROXIES', '')),
)));

if ($trustedProxies === []) {
    $trustedProxies = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}

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

    'trusted_proxies' => $trustedProxies,

    // Failed logins allowed per email/IP inside the window.
    // Five failures for one email from one address; a wider per-address limit
    // so one machine cannot work through a list of accounts.
    'login_max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
    'login_max_attempts_per_ip' => Env::int('LOGIN_MAX_ATTEMPTS_PER_IP', 25),
    'login_lockout_minutes' => Env::int('LOGIN_LOCKOUT_MINUTES', 15),
];
