<?php
declare(strict_types=1);

namespace Wiz\Controllers;

use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Domain\Enums\BookingStatus;
use Wiz\Repositories\BookingRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\SignedLink;

/**
 * The customer's side of a booking, reached from the link in their email.
 *
 * The signed token is the only credential, so every action re-verifies it.
 * A bad, edited or expired token simply 404s: there is nothing to explain to
 * someone who should not be here.
 */
final class PublicBookingController
{
    public function __construct(
        private readonly View $view,
        private readonly BookingRepository $bookings,
        private readonly BookingService $bookingsService,
        private readonly AvailabilityService $availability,
        private readonly SignedLink $links,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $booking = $this->booking((string) ($params['token'] ?? ''));
        $duration = $this->duration($booking);

        $days = [];
        $status = BookingStatus::tryFrom((string) $booking['status']);

        if ($status !== null && in_array($status, [BookingStatus::Requested, BookingStatus::Confirmed], true)) {
            $days = $this->availability->calendar(date('Y-m-d'), 14, $duration);
        }

        return $this->view->render('public/manage', [
            'title' => 'Your booking',
            'booking' => $booking,
            'token' => (string) ($params['token'] ?? ''),
            'days' => $days,
            'error' => $this->session->flash('error'),
            'success' => $this->session->flash('success'),
        ], 'layouts/public');
    }

    /**
     * @param array<string, string> $params
     */
    public function cancel(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $booking = $this->booking($token);

        $result = $this->bookingsService->transition(
            (int) $booking['id'],
            BookingStatus::Cancelled,
            null,
            'Cancelled by the customer from the emailed link.',
            $request->ip(),
        );

        $this->session->flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? 'Your appointment is cancelled. Thank you for letting us know.'
                : (string) ($result['error'] ?? 'That change could not be made.'),
        );

        throw new RedirectException('/booking/manage/' . $token);
    }

    /**
     * @param array<string, string> $params
     */
    public function reschedule(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $booking = $this->booking($token);
        $date = $request->string('date');
        $time = $request->string('time');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            $this->session->flash('error', 'Choose a day and a time first.');
            throw new RedirectException('/booking/manage/' . $token);
        }

        $result = $this->bookingsService->reschedule(
            (int) $booking['id'],
            $date,
            $time,
            null,
            $request->ip(),
        );

        $this->session->flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? 'Done — your appointment is now on ' . date('l j F', (int) strtotime($date)) . ' at ' . $time . '.'
                : (string) ($result['error'] ?? 'That time could not be kept.'),
        );

        throw new RedirectException('/booking/manage/' . $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(string $token): array
    {
        $claim = $this->links->verify($token);

        if ($claim === null || $claim['purpose'] !== 'booking') {
            throw HttpException::notFound('This link is not valid any more.');
        }

        $booking = $this->bookings->find($claim['id']);

        if ($booking === null) {
            throw HttpException::notFound('This booking no longer exists.');
        }

        return $booking;
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function duration(array $booking): int
    {
        $start = strtotime((string) $booking['booking_date'] . ' ' . (string) $booking['start_time']);
        $end = strtotime((string) $booking['booking_date'] . ' ' . (string) $booking['end_time']);

        if ($start === false || $end === false || $end <= $start) {
            return $this->availability->gridMinutes();
        }

        return (int) round(($end - $start) / 60);
    }
}
