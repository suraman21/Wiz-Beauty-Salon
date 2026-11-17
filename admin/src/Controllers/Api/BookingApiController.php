<?php
declare(strict_types=1);

namespace Wiz\Controllers\Api;

use Wiz\Core\HttpException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Validator;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\EthiopianDate;
use Wiz\Support\Origin;
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
        private readonly CategoryRepository $categories,
        private readonly RateLimitRepository $limits,
        private readonly BookingRepository $bookingRepo,
        private readonly string $appOrigin = '',
        private readonly string $uploadUrl = '/uploads',
        private readonly ?SignedLink $links = null,
    ) {
    }

    /**
     * GET /api/v1/services
     *
     * The public catalogue, as the salon has it right now: one flat list of
     * the services the salon offers, in the order staff put them. The React
     * site renders its Services page from this instead of a hard-coded list, so
     * a service added, renamed or hidden in the panel shows up (or disappears)
     * on the site immediately.
     *
     * @param array<string, string> $params
     */
    public function services(Request $request, array $params = []): Response
    {
        $this->guardRead('services', $request);

        $base = rtrim($this->uploadUrl, '/');
        $out = [];

        // What staff manage in the panel are the services the salon offers —
        // Hair, Nails, Makeup, Skin. They are bookable as they are: what happens
        // inside one is agreed with the customer at the salon, so there is no
        // price list or running time to publish.
        foreach ($this->categories->published() as $row) {
            $image = (string) ($row['image_path'] ?? '');

            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'amharic' => (string) ($row['amharic'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'show_on_home' => (int) ($row['show_on_home'] ?? 0) === 1,
                'image_url' => $image === '' ? null : $base . '/' . rawurlencode($image),
            ];
        }

        // A minute of caching keeps repeat page views cheap without leaving
        // staff staring at yesterday's list after they save a change.
        return Response::json(['services' => $out])
            ->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /api/v1/availability?date=YYYY-MM-DD&service_id=1
     * Also supports ?service_ids=1,2,3&duration=90&is_undecided=1 for multi/undecided bookings
     *
     * @param array<string, string> $params
     */
    public function availability(Request $request, array $params = []): Response
    {
        $this->guardRead('availability', $request);

        $date = $request->queryString('date', date('Y-m-d'));
        $resolved = $this->resolveDuration($request);

        if ($resolved === null) {
            throw HttpException::notFound('Unknown service.');
        }

        $duration = $resolved['duration'];
        $serviceInfo = $resolved['service'];

        $status = $this->availability->dayStatus($date);
        $slots = $status['open'] ? $this->availability->slotsForDate($date, $duration) : [];

        return Response::json([
            'date' => $date,
            // Additive: the same day in the Ethiopian calendar, and every slot
            // in the Ethiopian clock. Existing clients ignore what they do not
            // read; nothing that was here before has changed shape.
            'ethiopian' => EthiopianDate::wire($date),
            'slots_ethiopian' => array_map(
                static fn (string $slot): string => EthiopianDate::formatClock($slot),
                $slots,
            ),
            'service' => $serviceInfo,
            'open' => $status['open'],
            'reason' => $status['reason'],
            'slots' => $slots,
        ])->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /api/v1/calendar?service_id=1&from=YYYY-MM-DD&days=14
     * Also supports multi-service and undecided via service_ids/duration/is_undecided
     *
     * @param array<string, string> $params
     */
    public function calendar(Request $request, array $params = []): Response
    {
        $this->guardRead('calendar', $request);

        $resolved = $this->resolveDuration($request);

        if ($resolved === null) {
            throw HttpException::notFound('Unknown service.');
        }

        $duration = $resolved['duration'];
        $serviceInfo = $resolved['service'];
        $from = $request->queryString('from', date('Y-m-d'));
        $days = (int) $request->queryString('days', '14');

        // Each day gains its Ethiopian reading and its slots gain the Ethiopian
        // clock. The Gregorian `date` and `slots` stay exactly as they were —
        // they are what the site posts back and what the database stores.
        $rows = [];

        foreach ($this->availability->calendar($from, $days, $duration) as $row) {
            $slotList = is_array($row['slots'] ?? null) ? $row['slots'] : [];

            $row['ethiopian'] = EthiopianDate::wire(is_string($row['date'] ?? null) ? $row['date'] : null);
            $row['label'] = is_array($row['ethiopian']) ? $row['ethiopian']['label'] : null;
            $row['slots_ethiopian'] = array_map(
                static fn (string $slot): string => EthiopianDate::formatClock($slot),
                $slotList,
            );

            $rows[] = $row;
        }

        return Response::json([
            'service' => $serviceInfo,
            'days' => $rows,
        ])->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * Resolve a duration and a display service from the request, supporting
     * single, multi-service (service_ids) and undecided bookings.
     *
     * @return array{duration: int, service: array{id: int, name: string, duration_minutes: int}}|null
     */
    private function resolveDuration(Request $request): ?array
    {
        $categoryId = (int) $request->queryString('category_id');

        if ($categoryId > 0) {
            $category = $this->categories->find($categoryId);

            if ($category === null || (int) ($category['is_active'] ?? 0) !== 1) {
                return null;
            }

            $duration = $this->availability->gridMinutes();

            return [
                'duration' => $duration,
                'service' => [
                    'id' => $categoryId,
                    'name' => (string) $category['name'],
                    'duration_minutes' => $duration,
                ],
            ];
        }

        $durationParam = $request->queryString('duration');
        if ($durationParam !== '' && ctype_digit($durationParam) && (int) $durationParam > 0) {
            $d = (int) $durationParam;
            $d = max(15, min(480, $d));
            return [
                'duration' => $d,
                'service' => ['id' => 0, 'name' => 'Selected services', 'duration_minutes' => $d],
            ];
        }

        $isUndecided = $request->queryString('is_undecided') === '1' || $request->queryString('is_undecided') === 'true' || $request->queryString('isUndecided') === '1';

        $serviceIdsParam = $request->queryString('service_ids') !== '' ? $request->queryString('service_ids') : $request->queryString('serviceIds');
        if ($serviceIdsParam !== '') {
            $ids = [];
            $decoded = json_decode($serviceIdsParam, true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            } else {
                $parts = array_filter(array_map('trim', explode(',', $serviceIdsParam)));
                $ids = array_map('intval', $parts);
            }
            $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
            if ($ids !== []) {
                $total = 0;
                $names = [];
                $valid = 0;
                foreach ($ids as $sid) {
                    $svc = $this->services->find($sid);
                    if ($svc !== null && (int) ($svc['is_active'] ?? 0) === 1) {
                        $dur = (int) ($svc['duration_minutes'] ?? 0);
                        $total += $dur > 0 ? $dur : $this->availability->gridMinutes();
                        $names[] = (string) $svc['name'];
                        $valid++;
                    }
                }
                if ($valid > 0) {
                    $duration = $total > 0 ? $total : $this->availability->gridMinutes();
                    return [
                        'duration' => $duration,
                        'service' => [
                            'id' => $ids[0],
                            'name' => implode(' + ', $names),
                            'duration_minutes' => $duration,
                        ],
                    ];
                }
            }
        }

        $serviceId = (int) $request->queryString('service_id');
        if ($serviceId > 0) {
            $service = $this->services->find($serviceId);
            if ($service !== null && (int) ($service['is_active'] ?? 0) === 1) {
                $d = (int) ($service['duration_minutes'] ?? 0);
                $duration = $d > 0 ? $d : $this->availability->gridMinutes();
                return [
                    'duration' => $duration,
                    'service' => [
                        'id' => (int) $service['id'],
                        'name' => (string) $service['name'],
                        'duration_minutes' => $duration,
                    ],
                ];
            }
            return null;
        }

        if ($isUndecided) {
            $d = 60;
            return [
                'duration' => $d,
                'service' => ['id' => 0, 'name' => 'Not sure — we’ll help you decide', 'duration_minutes' => $d],
            ];
        }

        return null;
    }

    /**
     * A ceiling on reads, per visitor.
     *
     * The site loads the catalogue and a three-week calendar on every visit, so
     * the limit is high enough that a real customer never notices it. It only
     * applies when the request can be attributed to one address; behind an
     * unconfigured proxy a per-IP count would throttle everybody at once.
     */
    private function guardRead(string $bucket, Request $request): void
    {
        if (!$request->trustsClientIp()) {
            return;
        }

        if (!$this->limits->attempt('api-read:' . $bucket . ':' . $request->ip(), 120, 1)) {
            throw new HttpException(429, 'Too many requests just now. Please wait a moment and try again.');
        }
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
        Origin::assertSame($request, $this->appOrigin);

        $ip = $request->ip();

        if (!$this->limits->attempt('booking:' . $ip, 5, 60)) {
            throw new \Wiz\Core\HttpException(429, 'Too many booking attempts. Please call the salon.');
        }

        // Honeypot: a field real people never see. Bots fill it in.
        if ($request->string('website') !== '') {
            throw HttpException::validation('The booking could not be accepted.');
        }

        // Gather service selection: single, multi, or undecided
        $rawServiceIds = $request->string('service_ids') !== '' ? $request->string('service_ids') : $request->string('serviceIds');
        // Support JSON array body: Request::input may hold array
        if ($rawServiceIds === '' && is_array($request->input('service_ids') ?? null)) {
            $rawServiceIds = json_encode($request->input('service_ids'));
        }
        if ($rawServiceIds === '' && is_array($request->input('serviceIds') ?? null)) {
            $rawServiceIds = json_encode($request->input('serviceIds'));
        }
        $rawIsUndecided = $request->string('is_undecided') !== '' ? $request->string('is_undecided') : $request->string('isUndecided');
        // Also support JSON boolean
        if ($rawIsUndecided === '' && $request->input('is_undecided') !== null) {
            $v = $request->input('is_undecided');
            $rawIsUndecided = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        }

        // An Ethiopian-speaking client may send the day in its own calendar.
        // `date_ec` is additive: it is checked against `date` when both are
        // given, and it can stand alone when `date` is omitted. Whatever comes
        // in, Gregorian `date` is what reaches the database.
        $rawDateEc = $request->string('date_ec') !== ''
            ? $request->string('date_ec')
            : $request->string('ethiopian_date');
        $rawDate = $request->string('date');

        if ($rawDateEc !== '') {
            $parsedEc = EthiopianDate::parseIso($rawDateEc);

            if ($parsedEc === null) {
                throw HttpException::validation(
                    'That Ethiopian date does not exist. Check the day and the month.',
                );
            }

            $derived = EthiopianDate::toGregorian(
                $parsedEc['year'],
                $parsedEc['month'],
                $parsedEc['day'],
            )->format('Y-m-d');

            if ($rawDate === '') {
                $rawDate = $derived;
            } elseif ($rawDate !== $derived) {
                throw HttpException::validation(
                    'The Ethiopian and Gregorian dates do not match.',
                );
            }
        }

        $validator = new Validator(
            [
                'customer_name' => $request->string('name'),
                'customer_phone' => $request->string('phone'),
                'customer_email' => $request->string('email'),
                'service_id' => $request->string('service_id'),
                'service_ids' => $rawServiceIds,
                'category_id' => $request->string('category_id'),
                'is_undecided' => $rawIsUndecided,
                'date' => $rawDate,
                'time' => $request->string('time'),
                'notes' => $request->string('notes'),
            ],
            [
                'customer_name' => 'required|max:120',
                'customer_phone' => 'required|phone|max:40',
                'customer_email' => 'email|max:190',
                'service_id' => 'integer',
                'service_ids' => 'string',
                'category_id' => 'integer',
                'is_undecided' => 'string',
                'date' => 'required|date',
                'time' => 'required|time',
                'notes' => 'max:500',
            ],
            [
                'customer_name' => 'Name',
                'customer_phone' => 'Phone number',
                'customer_email' => 'Email address',
                'service_id' => 'Service',
                'category_id' => 'Service',
                'date' => 'Date',
                'time' => 'Time',
                'notes' => 'Notes',
            ],
        );

        if (!$validator->validate()) {
            throw HttpException::validation((string) $validator->firstError());
        }

        $input = $validator->validated();

        // Ensure at least one service choice or undecided is present
        $hasServiceId = isset($input['service_id']) && (string) $input['service_id'] !== '' && (int) $input['service_id'] > 0;
        $hasServiceIds = isset($input['service_ids']) && (string) $input['service_ids'] !== '' && (string) $input['service_ids'] !== '[]';
        $isUndecidedFlag = isset($input['is_undecided']) && in_array(strtolower((string) $input['is_undecided']), ['1', 'true', 'yes'], true);
        $hasCategoryId = isset($input['category_id']) && (string) $input['category_id'] !== '' && (int) $input['category_id'] > 0;
        if (!$hasServiceId && !$hasServiceIds && !$hasCategoryId && !$isUndecidedFlag) {
            throw HttpException::validation('Please choose a service.');
        }

        $result = $this->bookings->create([
            'customer_name' => (string) $input['customer_name'],
            'customer_phone' => (string) $input['customer_phone'],
            'customer_email' => (string) ($input['customer_email'] ?? ''),
            'service_id' => $hasServiceId ? (int) $input['service_id'] : null,
            'service_ids' => $hasServiceIds ? (string) $input['service_ids'] : null,
            'category_id' => $hasCategoryId ? (int) $input['category_id'] : null,
            'is_undecided' => $isUndecidedFlag ? 1 : 0,
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
            // Additive: what the customer just asked for, in both calendars.
            'date' => (string) $input['date'],
            'time' => (string) $input['time'],
            'ethiopian' => [
                'date' => EthiopianDate::wire((string) $input['date']),
                'time' => EthiopianDate::formatClock((string) $input['time'], true),
            ],
            'message' => 'Thank you. The salon will confirm your appointment shortly.',
        ], 201);
    }

    /**
     * POST /api/v1/bookings/recover
     *
     * Lets a customer who lost their manage link get it back with the phone
     * number OR email they booked with. Only upcoming requested/confirmed
     * bookings are returned, and the response is the same shape whether or not
     * anything was found — probing many identifiers teaches the caller nothing
     * useful. Accepts: phone, email, customer_phone, customer_email, identifier.
     *
     * @param array<string, string> $params
     */
    public function recover(Request $request, array $params = []): Response
    {
        Origin::assertSame($request, $this->appOrigin);

        $ip = $request->ip();

        if (!$this->limits->attempt('recover:' . $ip, 5, 300)) {
            throw new HttpException(429, 'Too many tries. Please wait a few minutes and try again.');
        }

        if ($request->string('website') !== '' || $request->string('_website') !== '') {
            throw HttpException::validation('The request could not be accepted.');
        }

        // Accept any of these keys so old and new clients both work.
        // Priority: identifier > phone > email > customer_* variants.
        $identifier = '';
        $candidates = [
            $request->string('identifier'),
            $request->string('phone'),
            $request->string('email'),
            $request->string('customer_phone'),
            $request->string('customer_email'),
        ];
        foreach ($candidates as $c) {
            if (trim($c) !== '') {
                $identifier = trim($c);
                break;
            }
        }
        // Also support raw JSON body fallback
        if ($identifier === '') {
            foreach (['identifier', 'phone', 'email', 'customer_phone', 'customer_email'] as $k) {
                $v = $request->input($k);
                if (is_string($v) && trim($v) !== '') {
                    $identifier = trim($v);
                    break;
                }
            }
        }

        if ($identifier === '') {
            throw HttpException::validation('Please enter your phone number or email address.');
        }

        // Validate as either phone or email — one must pass.
        $isEmail = str_contains($identifier, '@');
        if ($isEmail) {
            $validator = new Validator(
                ['customer_email' => $identifier],
                ['customer_email' => 'required|email|max:190'],
                ['customer_email' => 'Email address'],
            );
            if (!$validator->validate()) {
                throw HttpException::validation((string) $validator->firstError());
            }
            $identifier = (string) $validator->validated()['customer_email'];
            $rows = $this->bookingRepo->findRecoverableByIdentifier($identifier);
        } else {
            $validator = new Validator(
                ['customer_phone' => $identifier],
                ['customer_phone' => 'required|phone|max:40'],
                ['customer_phone' => 'Phone number'],
            );
            if (!$validator->validate()) {
                // Provide friendlier message that hints email is also accepted
                $msg = (string) $validator->firstError();
                throw HttpException::validation($msg . ' Or enter your email address.');
            }
            $identifier = (string) $validator->validated()['customer_phone'];
            $rows = $this->bookingRepo->findRecoverableByIdentifier($identifier);
        }

        $bookings = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $token = $this->links?->issue('booking', $id);
            $bookingDate = (string) ($row['booking_date'] ?? '');
            $bookingEc = EthiopianDate::toEthiopian($bookingDate);
            $startTime = substr((string) ($row['start_time'] ?? ''), 0, 5);
            $endTime = substr((string) ($row['end_time'] ?? ''), 0, 5);

            $bookings[] = [
                'reference' => (string) ($row['reference'] ?? ''),
                'service_name' => (string) ($row['service_name'] ?? ''),
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                // Additive: the same booking in the Ethiopian calendar and clock.
                'booking_date_ec' => $bookingEc === null
                    ? null
                    : EthiopianDate::iso($bookingEc['year'], $bookingEc['month'], $bookingEc['day']),
                'ethiopian' => EthiopianDate::wire($bookingDate),
                'start_time_label' => EthiopianDate::formatClock($startTime),
                'end_time_label' => EthiopianDate::formatClock($endTime),
                'status' => (string) ($row['status'] ?? ''),
                'manage_url' => $token === null ? null : rtrim($this->appOrigin, '/') . '/booking/manage/' . $token,
            ];
        }

        return Response::json([
            'ok' => true,
            'bookings' => $bookings,
            'count' => count($bookings),
        ]);
    }
}
