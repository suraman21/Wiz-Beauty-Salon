<?php
declare(strict_types=1);

use Wiz\Core\Env;

/**
 * Defaults only. The panel stores the authoritative mail configuration in the
 * `settings` table and can change it without a deploy; these values are the
 * fallback before anything has been saved there.
 *
 * The shape is the one Bulbula.et's Mailer expects: driver, host, port,
 * encryption, username, password, from_email, from_name — plus `salon_email`,
 * which WIZ needs because every enquiry and booking also notifies the salon.
 */
return [
    'driver' => Env::get('MAIL_DRIVER', 'smtp'),
    'host' => Env::get('MAIL_HOST', ''),
    'port' => Env::int('MAIL_PORT', 587),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    'from_email' => Env::get('MAIL_FROM_EMAIL', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'WIZ Beauty Salon'),
    'salon_email' => Env::get('MAIL_SALON_EMAIL', ''),
];
