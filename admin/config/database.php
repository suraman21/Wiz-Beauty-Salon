<?php
declare(strict_types=1);

use Wiz\Core\Env;

/**
 * The database connection.
 *
 * `timezone` is the session time zone applied to every connection. Without it
 * MySQL falls back to the operating system zone (which on the production host
 * is CEST), so NOW() and CURDATE() disagree with the salon's clock and every
 * "today" filter is wrong for part of the day. It defaults to the offset of
 * APP_TIMEZONE, and can be overridden when the database host cannot accept a
 * named zone.
 */
$appTimezone = (string) Env::get('APP_TIMEZONE', 'Africa/Addis_Ababa');
$offset = (new DateTimeImmutable('now', new DateTimeZone($appTimezone)))->format('P');

return [
    'host' => Env::get('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 3306),
    'name' => Env::get('DB_NAME', ''),
    'user' => Env::get('DB_USER', ''),
    'pass' => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'timezone' => Env::get('DB_TIMEZONE', $offset),
];
