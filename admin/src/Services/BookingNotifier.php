<?php
declare(strict_types=1);

namespace Wiz\Services;

use Throwable;
use Wiz\Core\Logger;
use Wiz\Core\Mailer;
use Wiz\Core\View;
use Wiz\Support\SignedLink;

/**
 * The emails a booking produces.
 *
 * Every method is best-effort: if the mail server is down the booking still
 * happens, and the failure lands in the log rather than in the customer's face.
 * The customer is only written to when they gave an address; the salon is
 * always told about a new request or a cancellation.
 */
final class BookingNotifier
{
    public function __construct(
        private readonly Mailer $mailer,
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

        $this->toCustomer($booking, [
            'title' => 'We have your request',
            'intro' => 'Thank you. The salon will call you to confirm this appointment — '
                . 'it is not booked until we do.',
            'rows' => $this->rows($booking, $when, $reference),
            'actionUrl' => $manage,
            'actionLabel' => 'View or change this request',
            'outro' => 'Keep this email. The link lets you cancel or move the appointment '
                . 'without calling, and it stays valid for 24 hours.',
        ], 'Request received · ' . $reference);

        $this->toSalon([
            'title' => 'New booking request',
            'intro' => (string) $booking['customer_name'] . ' asked for an appointment.',
            'rows' => array_merge($this->rows($booking, $when, $reference), [
                ['Phone', (string) $booking['customer_phone']],
            ]),
            'actionUrl' => $this->adminUrl((int) $booking['id']),
            'actionLabel' => 'Open the booking',
            'outro' => 'It is waiting for a yes or a no in the panel.',
        ], 'New booking request · ' . $when . ' · ' . $reference);
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function statusChanged(array $booking, string $to): void
    {
        $copy = match ($to) {
            'confirmed' => [
                'title' => 'Your appointment is confirmed',
                'intro' => 'We will see you at the salon. Please arrive a few minutes early.',
                'outro' => 'If you cannot make it, use the link above or call the salon.',
            ],
            'declined' => [
                'title' => 'We could not take that time',
                'intro' => 'We are sorry — the salon is not able to keep this appointment. '
                    . 'Please book another time, or call us and we will find one together.',
                'outro' => 'Nothing has been charged and no appointment is held.',
            ],
            'cancelled' => [
                'title' => 'Your appointment is cancelled',
                'intro' => 'This appointment has been cancelled. The time is now free for someone else.',
                'outro' => 'You can book again whenever you like.',
            ],
            'completed' => [
                'title' => 'Thank you for visiting',
                'intro' => 'Thank you for choosing the salon. We hope you love the result.',
                'outro' => 'We would be glad to see you again.',
            ],
            'no_show' => [
                'title' => 'We missed you',
                'intro' => 'The salon waited, but nobody arrived for this appointment.',
                'outro' => 'Call us if you would like to arrange another time.',
            ],
            default => [
                'title' => 'Your booking has been updated',
                'intro' => 'The salon has updated this appointment.',
                'outro' => 'Call us if anything looks wrong.',
            ],
        };

        $this->toCustomer($booking, [
            'title' => $copy['title'],
            'intro' => $copy['intro'],
            'rows' => $this->rows($booking, $this->when($booking), (string) $booking['reference']),
            'actionUrl' => $to === 'confirmed' ? $this->manageUrl($booking) : '',
            'actionLabel' => 'View or change this appointment',
            'outro' => $copy['outro'],
        ], $copy['title'] . ' · ' . (string) $booking['reference']);

        if ($to === 'cancelled') {
            $this->toSalon([
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

        $this->toCustomer($booking, [
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

        $this->toSalon([
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
    private function toCustomer(array $booking, array $content, string $subject): void
    {
        $to = trim((string) ($booking['customer_email'] ?? ''));

        if ($to === '') {
            return;
        }

        $this->deliver($to, $subject, $content);
    }

    /**
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function toSalon(array $content, string $subject): void
    {
        if (trim($this->salonEmail) === '') {
            return;
        }

        $this->deliver($this->salonEmail, $subject, $content);
    }

    /**
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function deliver(string $to, string $subject, array $content): void
    {
        try {
            $html = $this->view->capture('mail.booking', [
                'salonName' => $this->salonName,
                'title' => $content['title'],
                'intro' => $content['intro'],
                'rows' => $content['rows'],
                'actionUrl' => $content['actionUrl'],
                'actionLabel' => $content['actionLabel'],
                'outro' => $content['outro'],
            ]);

            $result = $this->mailer->send($to, $subject, $this->plain($content), $html);

            if ($result['ok'] !== true) {
                $this->logger->error('Mail failed: ' . (string) ($result['error'] ?? 'unknown'), [
                    'to' => $to,
                    'subject' => $subject,
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Mail exception: ' . $e->getMessage(), ['to' => $to]);
        }
    }

    /**
     * @param array{title: string, intro: string, rows: list<array{0: string, 1: string}>, actionUrl: string, actionLabel: string, outro: string} $content
     */
    private function plain(array $content): string
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
        $date = strtotime((string) $booking['booking_date']);

        return sprintf(
            '%s, %s–%s',
            $date === false ? (string) $booking['booking_date'] : date('l j F Y', $date),
            substr((string) $booking['start_time'], 0, 5),
            substr((string) $booking['end_time'], 0, 5),
        );
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function manageUrl(array $booking): string
    {
        $token = $this->links->issue('booking', (int) $booking['id']);

        return rtrim($this->appUrl, '/') . '/booking/manage/' . $token;
    }

    private function adminUrl(int $id): string
    {
        return rtrim($this->appUrl, '/') . '/admin/bookings/' . $id;
    }
}
