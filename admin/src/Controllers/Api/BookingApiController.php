<?php
declare(strict_types=1);

namespace Wiz\Controllers\Api;

use Wiz\Core\HttpException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Validator;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\SignedLink;

/**
 * Public JSON endpoints used by the React site.
 *
 * These are the only routes an anonymous visitor can reach, so they are
 * deliberately boring: read availability, accept one booking, and refuse
 * anything that does not look like a real person filling in the form.
 */
final class BookingApiController
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
        private readonly ServiceRepository $services,
        private readonly RateLimitRepository $limits,
        private readonly string $appOrigin = '',
        private readonly ?SignedLink $links = null,
    ) {
    }

    /**
     * GET /api/v1/services
     *
     * The public catalogue, as the salon has it right now. The React site uses
     * this instead of a hard-coded list, so a service added or hidden in the
     * panel shows up (or disappears) on the booking page immediately.
     *
     * @param array<string, string> $params
     */
    public function services(Request $request, array $params = []): Response
    {
        $rows = $this->services->activeWithCategory();
        $out = [];

        foreach ($rows as $row) {
            $duration = (int) ($row['duration_minutes'] ?? 0);
            $price = $row['price_cents'] === null ? null : (int) $row['price_cents'];

            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'category' => $row['category_name'] === null ? 'Other' : (string) $row['category_name'],
                'duration_minutes' => $duration > 0 ? $duration : $this->availability->gridMinutes(),
                'price_cents' => $price,
            ];
        }

        return Response::json(['services' => $out])
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    /**
     * GET /api/v1/availability?date=YYYY-MM-DD&service_id=1
     *
     * @param array<string, string> $params
     */
    public function availability(Request $request, array $params = []): Response
    {
        $serviceId = (int) $request->queryString('service_id');
        $date = $request->queryString('date', date('Y-m-d'));

        $service = $this->services->find($serviceId);

        if ($service === null || (int) ($service['is_active'] ?? 0) !== 1) {
            throw HttpException::notFound('Unknown service.');
        }

        $duration = (int) ($service['duration_minutes'] ?? 0);
        $status = $this->availability->dayStatus($date);
        $slots = $status['open'] ? $this->availability->slotsForDate($date, $duration) : [];

        return Response::json([
            'date' => $date,
            'service' => [
                'id' => (int) $service['id'],
                'name' => (string) $service['name'],
                'duration_minutes' => $duration > 0 ? $duration : $this->availability->gridMinutes(),
            ],
            'open' => $status['open'],
            'reason' => $status['reason'],
            'slots' => $slots,
        ])->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /api/v1/calendar?service_id=1&from=YYYY-MM-DD&days=14
     *
     * @param array<string, string> $params
     */
    public function calendar(Request $request, array $params = []): Response
    {
        $service = $this->services->find((int) $request->queryString('service_id'));

        if ($service === null || (int) ($service['is_active'] ?? 0) !== 1) {
            throw HttpException::notFound('Unknown service.');
        }

        $duration = (int) ($service['duration_minutes'] ?? 0);
        $duration = $duration > 0 ? $duration : $this->availability->gridMinutes();
        $from = $request->queryString('from', date('Y-m-d'));
        $days = (int) $request->queryString('days', '14');

        return Response::json([
            'service' => [
                'id' => (int) $service['id'],
                'name' => (string) $service['name'],
                'duration_minutes' => $duration,
            ],
            'days' => $this->availability->calendar($from, $days, $duration),
        ])->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * POST /api/v1/bookings
     *
     * Accepts JSON or a normal form post. The booking always starts as
     * "requested": the salon confirms it, which is what the phone call is for.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $this->assertSameOrigin($request);

        $ip = $request->ip();

        if (!$this->limits->attempt('booking:' . $ip, 5, 60)) {
            throw new \Wiz\Core\HttpException(429, 'Too many booking attempts. Please call the salon.');
        }

        // Honeypot: a field real people never see. Bots fill it in.
        if ($request->string('website') !== '') {
            throw HttpException::validation('The booking could not be accepted.');
        }

        $validator = new Validator(
            [
                'customer_name' => $request->string('name'),
                'customer_phone' => $request->string('phone'),
                'customer_email' => $request->string('email'),
                'service_id' => $request->string('service_id'),
                'date' => $request->string('date'),
                'time' => $request->string('time'),
                'notes' => $request->string('notes'),
            ],
            [
                'customer_name' => 'required|max:120',
                'customer_phone' => 'required|phone|max:40',
                'customer_email' => 'email|max:190',
                'service_id' => 'required|integer',
                'date' => 'required|date',
                'time' => 'required|time',
                'notes' => 'max:500',
            ],
            [
                'customer_name' => 'Name',
                'customer_phone' => 'Phone number',
                'customer_email' => 'Email address',
                'service_id' => 'Service',
                'date' => 'Date',
                'time' => 'Time',
                'notes' => 'Notes',
            ],
        );

        if (!$validator->validate()) {
            throw HttpException::validation((string) $validator->firstError());
        }

        $input = $validator->validated();

        $result = $this->bookings->create([
            'customer_name' => (string) $input['customer_name'],
            'customer_phone' => (string) $input['customer_phone'],
            'customer_email' => (string) ($input['customer_email'] ?? ''),
            'service_id' => (int) $input['service_id'],
            'date' => (string) $input['date'],
            'time' => (string) $input['time'],
            'notes' => (string) ($input['notes'] ?? ''),
        ], null, $ip);

        if ($result['ok'] !== true) {
            throw HttpException::validation((string) ($result['error'] ?? 'The booking could not be saved.'));
        }

        // The same signed link the email carries, so a customer who closes the
        // tab can still keep or move the appointment straight away.
        $token = $this->links?->issue('booking', (int) ($result['id'] ?? 0));

        return Response::json([
            'ok' => true,
            'reference' => (string) ($result['reference'] ?? ''),
            'status' => 'requested',
            'manage_url' => $token === null ? null : rtrim($this->appOrigin, '/') . '/booking/manage/' . $token,
            'message' => 'Thank you. The salon will confirm your appointment shortly.',
        ], 201);
    }

    /**
     * A browser sends Origin on every cross-site POST. If it names another site,
     * the request did not come from the salon's own pages, whatever the body
     * says. Requests without an Origin (curl, server-to-server) are allowed:
     * they cannot ride a visitor's cookies because this endpoint has none.
     */
    private function assertSameOrigin(Request $request): void
    {
        $origin = $request->header('Origin');

        if ($origin === null || $origin === '' || $this->appOrigin === '') {
            return;
        }

        $allowed = parse_url($this->appOrigin, PHP_URL_HOST);
        $given = parse_url($origin, PHP_URL_HOST);

        if (!is_string($allowed) || !is_string($given) || strcasecmp($given, $allowed) !== 0) {
            throw HttpException::forbidden('Requests must come from the salon website.');
        }
    }
}
