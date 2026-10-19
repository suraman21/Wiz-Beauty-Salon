<?php
declare(strict_types=1);

use Wiz\Core\Env;

/**
 * Defaults only. The admin panel stores the authoritative mail configuration in
 * the `settings` table and can enable or disable delivery at any time; these
 * values are the fallback when nothing has been saved there yet.
 */
return [
    'enabled' => Env::bool('MAIL_ENABLED', false),
    'active_profile' => Env::get('MAIL_ACTIVE_PROFILE', 'cpanel'),
    'from_email' => Env::get('MAIL_FROM_EMAIL', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'WIZ Beauty Salon'),

    'profiles' => [
        'cpanel' => [
            'label' => 'cPanel SMTP',
            'host' => Env::get('MAIL_HOST', 'mail.wiz.arkeonethiopia.com'),
            'port' => Env::int('MAIL_PORT', 587),
            'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
            'username' => Env::get('MAIL_USERNAME', ''),
            'password' => Env::get('MAIL_PASSWORD', ''),
        ],
        'external' => [
            'label' => 'External provider',
            'host' => Env::get('MAIL_ALT_HOST', ''),
            'port' => Env::int('MAIL_ALT_PORT', 587),
            'encryption' => Env::get('MAIL_ALT_ENCRYPTION', 'tls'),
            'username' => Env::get('MAIL_ALT_USERNAME', ''),
            'password' => Env::get('MAIL_ALT_PASSWORD', ''),
        ],
    ],
];
