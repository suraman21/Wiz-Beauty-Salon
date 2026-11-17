<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Domain\Enums\BookingStatus;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\Paginator;

/**
 * The bookings screen.
 *
 * Staff spend their day here, so the list answers the only two questions that
 * matter at a glance: who is coming next, and who is still waiting for a yes.
 */
final class BookingController
{
    /** Rows per page. A day of appointments fits twice over. */
    private const PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly BookingRepository $bookings,
        private readonly ServiceRepository $services,
        private readonly CategoryRepository $categories,
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookingService,
        private readonly Session $session,
        private readonly Auth $auth,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $status = $request->queryString('status', 'open');
        $range = $request->queryString('range', 'upcoming');
        $filters = ['status' => $status, 'range' => $range];

        $paginator = Paginator::of(
            max(1, (int) $request->queryString('page', '1')),
            self::PER_PAGE,
            $this->bookings->countSearch($filters),
        );

        $rows = $this->bookings->search($filters, $paginator->perPage, $paginator->offset());

        return $this->view->render('admin/bookings/index', [
            'title' => 'Bookings',
            'heading' => 'Bookings',
            'subheading' => 'Appointments, oldest waiting first.',
            'active' => 'bookings',
            'groups' => $this->groupByDate($rows),
            'status' => $status,
            'range' => $range,
            'pending' => $this->bookings->countAwaitingApproval(),
            'paginator' => $paginator,
            'query' => $filters,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        return $this->form(null, $request);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $booking = $this->bookings->find((int) ($params['id'] ?? 0));

        if ($booking === null) {
            throw HttpException::notFound('That booking no longer exists.');
        }

        $status = BookingStatus::tryFrom((string) $booking['status']);

        return $this->view->render('admin/bookings/show', [
            'title' => 'Booking ' . (string) $booking['reference'],
            'heading' => 'Booking ' . (string) $booking['reference'],
            'subheading' => $status?->label() ?? (string) $booking['status'],
            'active' => 'bookings',
            'backHref' => '/admin/bookings',
            'booking' => $booking,
            'status' => $status,
            'transitions' => $status?->allowedTransitions() ?? [],
            'history' => $this->bookings->history((int) $booking['id']),
            'slots' => $request->queryString('date') !== ''
                ? $this->slotsFor($request->queryString('date'), $booking)
                : [],
            'slotDate' => $request->queryString('date'),
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $validator = new Validator(
            [
                'customer_name' => $request->string('customer_name'),
                'customer_phone' => $request->string('customer_phone'),
                'customer_email' => $request->string('customer_email'),
                'category_id' => $request->string('category_id'),
                'date' => $request->string('date'),
                'time' => $request->string('time'),
                'notes' => $request->string('notes'),
            ],
            [
                'customer_name' => 'required|max:120',
                'customer_phone' => 'required|phone|max:40',
                'customer_email' => 'email|max:190',
                'category_id' => 'required|integer',
                'date' => 'required|date',
                'time' => 'required|time',
                'notes' => 'max:500',
            ],
            [
                'customer_name' => 'Customer name',
                'customer_phone' => 'Phone number',
                'customer_email' => 'Email address',
                'category_id' => 'Service',
                'date' => 'Date',
                'time' => 'Time',
                'notes' => 'Notes',
            ],
        );

        if (!$validator->validate()) {
            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException($this->backToForm($request));
        }

        $input = $validator->validated();

        $result = $this->bookingService->create([
            'customer_name' => (string) $input['customer_name'],
            'customer_phone' => (string) $input['customer_phone'],
            'customer_email' => (string) ($input['customer_email'] ?? ''),
            'category_id' => (int) $input['category_id'],
            'date' => (string) $input['date'],
            'time' => (string) $input['time'],
            'notes' => (string) ($input['notes'] ?? ''),
        ], $this->auth->id(), $request->ip());

        if ($result['ok'] !== true) {
            $this->session->flash('error', (string) ($result['error'] ?? 'The booking could not be saved.'));

            throw new RedirectException($this->backToForm($request));
        }

        $this->session->flash(
            'success',
            sprintf('Booking %s saved. Confirm it once you have spoken to the customer.', (string) ($result['reference'] ?? '')),
        );

        throw new RedirectException('/admin/bookings/' . (int) ($result['id'] ?? 0));
    }

    /**
     * @param array<string, string> $params
     */
    public function transition(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $target = BookingStatus::tryFrom($request->string('status'));

        if ($target === null) {
            throw HttpException::notFound('Unknown status.');
        }

        $result = $this->bookingService->transition(
            $id,
            $target,
            $this->auth->id(),
            $request->string('note') === '' ? null : $request->string('note'),
            $request->ip(),
        );

        if ($result['ok'] !== true) {
            $this->session->flash('error', (string) ($result['error'] ?? 'That change is not possible.'));

            throw new RedirectException('/admin/bookings/' . $id);
        }

        $this->session->flash('success', 'Booking marked as ' . strtolower($target->label()) . '.');

        throw new RedirectException('/admin/bookings/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function reschedule(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        $result = $this->bookingService->reschedule(
            $id,
            $request->string('date'),
            $request->string('time'),
            $this->auth->id(),
            $request->ip(),
        );

        if ($result['ok'] !== true) {
            $this->session->flash('error', (string) ($result['error'] ?? 'That time is not available.'));

            throw new RedirectException('/admin/bookings/' . $id . '?date=' . rawurlencode($request->string('date')));
        }

        $this->session->flash('success', 'Booking moved. The old time is free again.');

        throw new RedirectException('/admin/bookings/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function notes(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        if ($this->bookings->find($id) === null) {
            throw HttpException::notFound('That booking no longer exists.');
        }

        $this->bookingService->saveAdminNotes($id, $request->string('admin_notes'));
        $this->session->flash('success', 'Note saved.');

        throw new RedirectException('/admin/bookings/' . $id);
    }

    /**
     * @param array<string, mixed>|null $booking
     */
    private function form(?array $booking, Request $request): Response
    {
        $date = $request->queryString('date', date('Y-m-d'));
        $serviceId = (int) $request->queryString('category_id');

        return $this->view->render('admin/bookings/form', [
            'title' => 'New booking',
            'heading' => 'New booking',
            'subheading' => 'For customers who call or walk in.',
            'active' => 'bookings',
            'backHref' => '/admin/bookings',
            'services' => $this->categories->active(),
            'date' => $date,
            'serviceId' => $serviceId,
            'slots' => $serviceId > 0 ? $this->slotsForService($date, $serviceId) : [],
            'dayStatus' => $this->availability->dayStatus($date),
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @param array<string, mixed> $booking
     * @return list<string>
     */
    private function slotsFor(string $date, array $booking): array
    {
        $duration = (int) ($booking['duration_minutes'] ?? 0);
        if ($duration > 0) {
            return $this->availability->slotsForDate($date, $duration);
        }
        if (!empty($booking['is_undecided'])) {
            return $this->availability->slotsForDate($date, 60);
        }
        if (!empty($booking['service_ids'])) {
            $ids = json_decode((string) $booking['service_ids'], true);
            if (is_array($ids) && $ids !== []) {
                $total = 0;
                foreach ($ids as $sid) {
                    $svc = $this->services->find((int) $sid);
                    if ($svc !== null) {
                        $dur = (int) ($svc['duration_minutes'] ?? 0);
                        $total += $dur > 0 ? $dur : $this->availability->gridMinutes();
                    }
                }
                if ($total > 0) {
                    return $this->availability->slotsForDate($date, $total);
                }
            }
        }
        $serviceId = (int) ($booking['service_id'] ?? 0);

        return $serviceId > 0 ? $this->slotsForService($date, $serviceId) : [];
    }

    /**
     * @return list<string>
     */
    private function slotsForService(string $date, int $serviceId): array
    {
        // A service has no published running time any more: every appointment
        // claims one cell of the salon's grid.
        $service = $this->categories->find($serviceId);

        if ($service === null) {
            return [];
        }

        return $this->availability->slotsForDate($date, $this->availability->gridMinutes());
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{date: string, label: string, items: list<array<string, mixed>>}>
     */
    private function groupByDate(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $date = (string) $row['booking_date'];

            if (!isset($groups[$date])) {
                $groups[$date] = ['date' => $date, 'label' => $this->dateLabel($date), 'items' => []];
            }

            $groups[$date]['items'][] = $row;
        }

        return array_values($groups);
    }

    /**
     * The heading over a day's appointments, in the calendar the salon uses.
     *
     * "Today · Hid 8, 2019 (17 Nov)" — Ethiopian date first because that is
     * how staff say the day, with the Gregorian one in brackets so a booking
     * taken on the phone in either calendar can still be matched. The stored
     * value stays Gregorian; only the label changes.
     */
    private function dateLabel(string $date): string
    {
        $timestamp = strtotime($date . ' 12:00:00');

        $label = $timestamp === false
            ? $date
            : ethiopian_date($date, 'medium') . ' (' . date('j M', $timestamp) . ')';

        return match ($date) {
            date('Y-m-d') => 'Today · ' . $label,
            date('Y-m-d', strtotime('+1 day')) => 'Tomorrow · ' . $label,
            default => $label,
        };
    }

    private function backToForm(Request $request): string
    {
        return '/admin/bookings/new?date=' . rawurlencode($request->string('date'))
            . '&category_id=' . rawurlencode($request->string('category_id'));
    }
}
