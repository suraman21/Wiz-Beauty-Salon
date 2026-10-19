<?php
declare(strict_types=1);

namespace Wiz\Services;

/**
 * The closed list of emails the salon can design.
 *
 * A template key can never come from a request: the controller looks the key up
 * here first, so an unknown value cannot be used to render arbitrary mail.
 *
 * Cloned from Bulbula.et's `EmailTemplateCatalog`; the keys are WIZ's real
 * emails instead of the review platform's twenty.
 */
final class EmailTemplateCatalog
{
    public const LABELS = [
        'booking_requested' => 'Booking request — customer',
        'booking_confirmed' => 'Booking confirmed — customer',
        'booking_declined' => 'Booking declined — customer',
        'booking_cancelled' => 'Booking cancelled — customer',
        'booking_completed' => 'Thank you after a visit — customer',
        'booking_missed' => 'Missed appointment — customer',
        'booking_rescheduled' => 'Booking moved — customer',
        'booking_updated' => 'Booking updated — customer',
        'salon_new_booking' => 'New booking — salon',
        'salon_booking_moved' => 'Booking moved — salon',
        'salon_booking_cancelled' => 'Booking cancelled — salon',
        'message_received' => 'Website message — salon',
        'staff_password_changed' => 'Password changed — staff',
    ];

    /**
     * Code emails must carry {{code}} and {{minutes}}; every other type carries
     * {{message}}. WIZ has no code emails today, but the rule is part of the
     * catalogue contract and is kept so a future code email cannot skip it.
     */
    public static function isCode(string $key): bool
    {
        return $key === 'registration_code' || str_starts_with($key, 'otp_');
    }

    public static function required(string $key): string
    {
        if (!isset(self::LABELS[$key])) {
            throw new \InvalidArgumentException('Unknown email type.');
        }

        return self::isCode($key) ? 'code' : 'message';
    }

    /**
     * @return array{code: string, minutes: string, message: string, subject: string, name: string}
     */
    public static function example(string $key): array
    {
        self::required($key);

        return [
            'code' => '123456',
            'minutes' => '10',
            'message' => 'A sample WIZ Beauty Salon update. No real customer information is shown in this preview.',
            'subject' => self::LABELS[$key] . ' — WIZ Beauty Salon',
            'name' => 'Sample customer',
        ];
    }

    /**
     * @return array{subject: string, html: string, text: string}
     */
    public static function initial(string $key): array
    {
        $required = self::required($key);
        $body = $required === 'code'
            ? 'Your code is {{code}}. Enter it within {{minutes}} minutes. Never share it.'
            : '{{message}}';
        $label = htmlspecialchars(self::LABELS[$key], ENT_QUOTES, 'UTF-8');

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf6f1;padding:26px;font-family:Arial,sans-serif">'
            . '<tr><td align="center"><table role="presentation" width="560" style="max-width:560px;background:#ffffff;border-radius:16px;padding:28px">'
            . '<tr><td><p style="color:#9c4a2f;font-weight:bold;font-size:20px">WIZ Beauty Salon</p>'
            . '<h1 style="color:#2b2320;font-size:25px">' . $label . '</h1>'
            . '<p style="color:#4a403b;font-size:16px;line-height:1.6">' . $body . '</p>'
            . '<p style="color:#7d726b;font-size:12px">If you did not expect this message, you can ignore it.</p>'
            . '</td></tr></table></td></tr></table>';

        return ['subject' => '{{subject}}', 'html' => $html, 'text' => $body];
    }
}
