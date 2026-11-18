<?php
declare(strict_types=1);

namespace Wiz\Services;

use Throwable;
use Wiz\Contracts\MailTransport;
use Wiz\Core\Logger;
use Wiz\Core\View;
use Wiz\Support\EthiopianDate;
use Wiz\Support\SignedLink;

/**
 * The emails a booking produces.
 *
 * Each one is built twice: a complete fallback from the booking itself, and the
 * active administrator design (if any) rendered with a single `{{message}}`
 * value. The fallback is what goes out when no design is published, so a broken
 * or missing template can never stop a confirmation.
 *
 * Every method is best-effort: if the mail server is down the booking still
 * happens, and the failure lands in the log rather than in the customer's face.
 */
final class BookingNotifier
{
    public function __construct(
        private readonly MailTransport $transport,
        private readonly EmailTemplateService $templates,
        private readonly View $view,
        private readonly SignedLink $links,
        private readonly Logger $logger,
        private readonly string $appUrl,
        private readonly string $salonEmail,
        private readonly string $salonName = 'WIZ Beauty Salon',
    ) {
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function requested(array $booking): void
    {
        $reference = (string) $booking['reference'];
        $when = $this->when($booking);
        $manage = $this->manageUrl($booking);

        $this->toCustomer($booking, 'booking_requested', [
            'title' => 'We have your request',
            'intro' => 'Thank you. The salon will call you to confirm this appointment — '
                . 'it is not booked until we do.',
            'rows' => $this->rows($booking, $when, $reference),
            'actionUrl' => $manage,
            'actionLabel' => 'View or change this request',
            'outro' => 'Keep this email. The link lets you cancel or move the appointment '
                . 'without calling, and it stays valid for 24 hours.',
        ], 'Request received · ' . $reference);

        $this->toSalon('salon_new_booking', [
            'title' => 'New booking request',
            'intro' => (string) $booking['customer_name'] . ' asked for an appointment.',
            'rows' => array_merge($this->rows($booking, $when, $reference), [
                ['Phone', (string) $booking['customer_phone']],
            ]),
            'actionUrl' => $this->adminUrl((int) $booking['id']),
            'actionLabel' => 'Open the booking',
            'outro' => 'It is waiting for a yes or a no in the panel.',
        ], 'New booking request · ' . $when . ' · ' . $reference, (string) ($booking['customer_email'] ?? ''));
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function statusChanged(array $booking, string $to): void
    {
        $copy = match ($to) {
            'confirmed' => [
                'key' => 'booking_confirmed',
                'title' => 'Your appointment is confirmed',
                'intro' => 'We will see you at the salon. Please arrive a few minutes early.',
                'outro' => 'If you cannot make it, use the link above or call the salon.',
            ],
            'declined' => [
                'key' => 'booking_declined',
                'title' => 'We could not take that time',
                'intro' => 'We are sorry — the salon is not able to keep this appointment. '
                    . 'Please book another time, or call us and we will find one together.',
                'outro' => 'Nothing has been charged and no appointment is held.',
            ],
            'cancelled' => [
                'key' => 'booking_cancelled',
                'title' => 'Your appointment is cancelled',
                'intro' => 'This appointment has been cancelled. The time is now free for someone else.',
                'outro' => 'You can book again whenever you like.',
            ],
            'completed' => [
                'key' => 'booking_completed',
                'title' => 'Thank you for visiting',
                'intro' => 'Thank you for choosing the salon. We hope you love the result.',
                'outro' => 'We would be glad to see you again.',
            ],
            'no_show' => [
                'key' => 'booking_missed',
                'title' => 'We missed you',
                'intro' => 'The salon waited, but nobody arrived for this appointment.',
                'outro' => 'Call us if you would like to arrange another time.',
            ],
            default => [
                'key' => 'booking_updated',
                'title' => 'Your booking has been updated',
                'intro' => 'The salon has updated this appointment.',
                'outro' => 'Call us if anything looks wrong.',
            ],
        };

        $this->toCustomer($booking, $copy['key'], [
            'title' => $copy['title'],
            'intro' => $copy['intro'],
            'rows' => $this->rows($booking, $this->when($booking), (string) $booking['reference']),
            'actionUrl' => $to === 'confirmed' ? $this->manageUrl($booking) : '',
            'actionLabel' => 'View or change this appointment',
            'outro' => $copy['outro'],
        ], $copy['title'] . ' · ' . (string) $booking['reference']);

        if ($to === 'cancelled') {
            $this->toSalon('salon_booking_cancelled', [
                'title' => 'Booking cancelled',
                'intro' => (string) $booking['customer_name'] . ' cancelled their appointment. '
                    . 'The time is free again.',
                'rows' => $this->rows($booking, $this->when($booking), (string) $booking['reference']),
                'actionUrl' => $this->adminUrl((int) $booking['id']),
                'actionLabel' => 'Open the booking',
                'outro' => '',
            ], 'Cancelled · ' . $this->when($booking) . ' · ' . (string) $booking['reference']);
        }
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function rescheduled(array $booking, string $from): void
    {
        $reference = (string) $booking['reference'];

        $this->toCustomer($booking, 'booking_rescheduled', [
            'title' => 'Your appointment has moved',
            'intro' => 'The appointment is now at the time below. The old time is free again.',
            'rows' => array_merge(
                [['Was', $from]],
                $this->rows($booking, $this->when($booking), $reference),
            ),
            'actionUrl' => $this->manageUrl($booking),
            'actionLabel' => 'View or change this appointment',
            'outro' => 'If the new time is not right, use the link above or call the salon.',
        ], 'Moved · ' . $this->when($booking) . ' · ' . $reference);

        $this->toSalon('salon_booking_moved', [
            'title' => 'Booking moved',
            'intro' => (string) $booking['customer_name'] . ' moved their appointment.',
            'rows' => array_merge(
                [['Was', $from]],
                $this->rows($booking, $this->when($booking), $reference),
            ),
            'actionUrl' => $this->adminUrl((int) $booking['id']),
            'actionLabel' => 'Open the booking',
            'outro' => '',
        ], 'Moved · ' . $this->when($booking) . ' · ' . $reference);
    }

    /**
     * @param array<string, mixed> $booking
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function toCustomer(array $booking, string $key, array $content, string $subject): void
    {
        $to = trim((string) ($booking['customer_email'] ?? ''));

        if ($to === '') {
            return;
        }

        $this->deliver($key, $to, $subject, $content, (string) ($booking['customer_name'] ?? ''));
    }

    /**
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function toSalon(string $key, array $content, string $subject, string $replyTo = ''): void
    {
        if (trim($this->salonEmail) === '') {
            return;
        }

        $this->deliver($key, $this->salonEmail, $subject, $content, '', $replyTo);
    }

    /**
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function deliver(string $key, string $to, string $subject, array $content, string $name, string $replyTo = ''): void
    {
        try {
            $html = $this->view->capture('mail.booking', [
                'salonName' => $this->salonName,
                'logoUrl' => $this->appUrl === '' ? '' : rtrim($this->appUrl, '/') . '/logo.png',
                'title' => $content['title'],
                'intro' => $content['intro'],
                'rows' => $content['rows'],
                'actionUrl' => $content['actionUrl'],
                'actionLabel' => $content['actionLabel'],
                'outro' => $content['outro'],
            ]);

            $text = $this->plainText($content);

            $message = $this->templates->render($key, ['message' => $text, 'name' => $name], [
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ]);

            $result = $this->transport->send(
                $to,
                $message['subject'],
                $message['html'],
                $message['text'],
                $replyTo === '' ? null : $replyTo,
            );

            if (($result['ok'] ?? false) !== true) {
                $this->logger->warning('Booking email not accepted', [
                    'type' => $key,
                    'error' => (string) ($result['error'] ?? 'unknown'),
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Booking email failed', ['type' => $key, 'reason' => $e::class]);
        }
    }

    /**
     * The same content as the HTML fallback, as readable plain text — this is
     * also what `{{message}}` contains inside a published design.
     *
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function plainText(array $content): string
    {
        $lines = [$content['title'], '', $content['intro'], ''];

        foreach ($content['rows'] as [$label, $value]) {
            $lines[] = $label . ': ' . $value;
        }

        if ($content['actionUrl'] !== '') {
            $lines[] = '';
            $lines[] = $content['actionLabel'] . ': ' . $content['actionUrl'];
        }

        if ($content['outro'] !== '') {
            $lines[] = '';
            $lines[] = $content['outro'];
        }

        $lines[] = '';
        $lines[] = $this->salonName;

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $booking
     * @return list<array{0: string, 1: string}>
     */
    private function rows(array $booking, string $when, string $reference): array
    {
        return [
            ['Reference', $reference],
            ['Service', (string) $booking['service_name']],
            ['When', $when],
        ];
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function when(array $booking): string
    {
        $date = (string) $booking['booking_date'];
        $start = substr((string) $booking['start_time'], 0, 5);
        $end = substr((string) $booking['end_time'], 0, 5);

        // The same reading the site and the panel use: the Ethiopian date and
        // the Ethiopian clock, with the Gregorian day and 24-hour time beside
        // it, because this line is printed in an email the customer keeps.
        return sprintf(
            '%s (%s), %s–%s',
            EthiopianDate::format($date, 'long', $date),
            EthiopianDate::formatGregorian($date, $date),
            EthiopianDate::formatClock($start),
            EthiopianDate::formatClock($end),
        );
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function manageUrl(array $booking): string
    {
        try {
            $token = $this->links->issue('booking', (int) $booking['id'], 86_400);
        } catch (Throwable) {
            return '';
        }

        return rtrim($this->appUrl, '/') . '/booking/manage/' . $token;
    }

    private function adminUrl(int $id): string
    {
        return rtrim($this->appUrl, '/') . '/admin/bookings/' . $id;
    }
}
