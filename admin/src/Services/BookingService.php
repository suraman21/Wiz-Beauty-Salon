<?php
declare(strict_types=1);

namespace Wiz\Services;

use Wiz\Core\Database;
use Wiz\Core\Logger;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Domain\Booking\SlotUnavailable;
use Wiz\Domain\Enums\BookingStatus;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\ServiceRepository;

/**
 * Creating and moving bookings.
 *
 * Two rules live here, and only here:
 *
 * 1. A booking only exists if its grid cells could be claimed. The insert and
 *    the claims happen in one transaction, so a failure leaves nothing behind.
 * 2. A status change that releases the slot deletes the claims in the same
 *    transaction, which is what makes a cancelled time bookable again.
 */
final class BookingService
{
    public function __construct(
        private readonly Database $db,
        private readonly BookingRepository $bookings,
        private readonly BookingSlotRepository $slots,
        private readonly ServiceRepository $services,
        private readonly AvailabilityService $availability,
        private readonly SlotCalculator $calculator,
        private readonly ActivityLogRepository $activity,
        private readonly Logger $logger,
        private readonly ?BookingNotifier $notifier = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, id?: int, reference?: string, error?: string}
     */
    public function create(array $input, ?int $userId, string $ip): array
    {
        $date = (string) ($input['date'] ?? '');
        $time = (string) ($input['time'] ?? '');

        $status = $this->availability->dayStatus($date);

        if (!$status['open']) {
            return ['ok' => false, 'error' => (string) $status['reason']];
        }

        // Resolve services: undecided, multi, or single
        $isUndecidedInput = $input['is_undecided'] ?? $input['isUndecided'] ?? false;
        $isUndecided = $isUndecidedInput === true || $isUndecidedInput === '1' || $isUndecidedInput === 1 || $isUndecidedInput === 'true';

        $serviceIdsInput = $input['service_ids'] ?? $input['serviceIds'] ?? null;
        $serviceIds = [];
        if (is_string($serviceIdsInput) && $serviceIdsInput !== '') {
            $decoded = json_decode($serviceIdsInput, true);
            if (is_array($decoded)) {
                $serviceIds = array_map('intval', $decoded);
            } else {
                // comma separated
                $parts = array_filter(array_map('trim', explode(',', $serviceIdsInput)));
                $serviceIds = array_map('intval', $parts);
            }
        } elseif (is_array($serviceIdsInput)) {
            $serviceIds = array_map('intval', $serviceIdsInput);
        }
        // also support single service_id inside service_ids array via service_id fallback
        $singleId = (int) ($input['service_id'] ?? 0);
        // If no explicit service_ids but singleId provided, treat as single (handled below)
        // If undecided flag or no service info at all -> undecided
        $isUndecided = $isUndecided || (empty($serviceIds) && $singleId === 0);

        $serviceId = null;
        $serviceName = '';
        $priceCents = null;
        $duration = 0;
        $serviceIdsJson = null;
        $isUndecidedFlag = 0;

        if ($isUndecided) {
            $duration = 60;
            $serviceName = 'Not sure — we’ll help you decide';
            $priceCents = null;
            $serviceId = null;
            $serviceIdsJson = null;
            $isUndecidedFlag = 1;
        } elseif (!empty($serviceIds)) {
            $services = [];
            $totalDuration = 0;
            $totalPrice = 0;
            $hasPrice = false;
            $hasNullPrice = false;
            $names = [];
            // dedupe and filter
            $serviceIds = array_values(array_unique(array_filter($serviceIds, static fn (int $id): bool => $id > 0)));
            if (count($serviceIds) === 0) {
                return ['ok' => false, 'error' => 'Please select at least one service.'];
            }
            if (count($serviceIds) > 5) {
                return ['ok' => false, 'error' => 'You can book up to 5 services at once.'];
            }
            foreach ($serviceIds as $sid) {
                $svc = $this->services->find($sid);
                if ($svc === null || (int) ($svc['is_active'] ?? 0) !== 1) {
                    return ['ok' => false, 'error' => 'One of the selected services is not available.'];
                }
                $services[] = $svc;
                $totalDuration += $this->durationOf($svc);
                $names[] = (string) $svc['name'];
                if ($svc['price_cents'] === null) {
                    $hasNullPrice = true;
                } else {
                    $totalPrice += (int) $svc['price_cents'];
                    $hasPrice = true;
                }
            }
            if ($hasNullPrice) {
                $priceCents = null;
            } else {
                $priceCents = $hasPrice ? $totalPrice : null;
            }
            $duration = $totalDuration > 0 ? $totalDuration : $this->availability->gridMinutes();
            $serviceName = implode(' + ', $names);
            $serviceId = (int) $services[0]['id'];
            $serviceIdsJson = json_encode($serviceIds);
            $isUndecidedFlag = 0;
        } else {
            $serviceId = $singleId;
            $service = $this->services->find($serviceId);
            if ($service === null || (int) ($service['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'error' => 'That service is not available any more.'];
            }
            $duration = $this->durationOf($service);
            $serviceName = (string) $service['name'];
            $priceCents = $service['price_cents'] === null ? null : (int) $service['price_cents'];
            $serviceIdsJson = null;
            $isUndecidedFlag = 0;
        }

        $available = $this->availability->slotsForDate($date, $duration);

        if (!in_array($time, $available, true)) {
            return ['ok' => false, 'error' => 'That time is no longer available. Please choose another.'];
        }

        $reference = $this->reference();
        $start = $time . ':00';
        $end = $this->calculator->endTime($time, $duration) . ':00';

        try {
            $id = $this->db->transaction(function (Database $db) use (
                $input,
                $reference,
                $date,
                $time,
                $duration,
                $start,
                $end,
                $serviceId,
                $serviceName,
                $priceCents,
                $serviceIdsJson,
                $isUndecidedFlag
            ): int {
                $id = $this->bookings->create([
                    'reference' => $reference,
                    'customer_name' => (string) ($input['customer_name'] ?? ''),
                    'customer_email' => $this->nullable($input['customer_email'] ?? null),
                    'customer_phone' => (string) ($input['customer_phone'] ?? ''),
                    'service_id' => $serviceId,
                    'service_ids' => $serviceIdsJson,
                    'service_name' => $serviceName,
                    'price_cents' => $priceCents,
                    'duration_minutes' => $duration,
                    'is_undecided' => $isUndecidedFlag,
                    'booking_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => BookingStatus::Requested->value,
                    'notes' => $this->nullable($input['notes'] ?? null),
                ]);

                $cells = $this->calculator->cellsFor($time, $duration, $this->availability->gridMinutes());

                $claims = array_map(static fn (string $cell): string => $date . ' ' . $cell . ':00', $cells);

                if (!$this->slots->claim($id, $claims)) {
                    throw new SlotUnavailable('slot taken');
                }

                return $id;
            });
        } catch (SlotUnavailable) {
            return ['ok' => false, 'error' => 'Someone just booked that time. Please choose another.'];
        }

        $this->bookings->addHistory($id, null, BookingStatus::Requested->value, $userId, null);

        $this->activity->record($userId, 'booking.created', 'booking', $id, ['reference' => $reference], $ip);
        $this->logger->info('Booking created', ['reference' => $reference, 'date' => $date, 'time' => $time]);

        // Tell the customer and the salon. Best-effort: a mail server having a
        // bad morning must never lose a booking that was already saved.
        $fresh = $this->bookings->find($id);

        if ($fresh !== null) {
            $this->notifier?->requested($fresh);
        }

        return ['ok' => true, 'id' => $id, 'reference' => $reference];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function transition(int $bookingId, BookingStatus $to, ?int $userId, ?string $note = null, string $ip = ''): array
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null) {
            return ['ok' => false, 'error' => 'That booking no longer exists.'];
        }

        $from = BookingStatus::tryFrom((string) $booking['status']);

        if ($from === null) {
            return ['ok' => false, 'error' => 'That booking has an unknown status.'];
        }

        if ($from === $to) {
            return ['ok' => true];
        }

        if (!$from->canTransitionTo($to)) {
            return [
                'ok' => false,
                'error' => sprintf('A %s booking cannot be marked as %s.', strtolower($from->label()), strtolower($to->label())),
            ];
        }

        $this->db->transaction(function (Database $db) use ($booking, $to): void {
            if (!$to->occupiesSlot()) {
                $this->slots->release((int) $booking['id']);
            }

            $this->bookings->updateStatus((int) $booking['id'], $to->value);
        });

        $this->bookings->addHistory($bookingId, $from->value, $to->value, $userId, $note);
        $this->activity->record($userId, 'booking.' . $to->value, 'booking', $bookingId, [], $ip);

        if (in_array($to, [BookingStatus::Confirmed, BookingStatus::Declined, BookingStatus::Cancelled], true)) {
            $fresh = $this->bookings->find($bookingId);

            if ($fresh !== null) {
                $this->notifier?->statusChanged($fresh, $to->value);
            }
        }

        return ['ok' => true];
    }

    /**
     * Move an existing booking to another time.
     *
     * The booking's own cells are released first so that availability is
     * computed against the rest of the day; if the new time is not free the
     * transaction rolls back and the old appointment is untouched.
     *
     * @return array{ok: bool, error?: string}
     */
    public function reschedule(int $bookingId, string $date, string $time, ?int $userId, string $ip = ''): array
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null) {
            return ['ok' => false, 'error' => 'That booking no longer exists.'];
        }

        $status = BookingStatus::tryFrom((string) $booking['status']);

        if ($status === null || !$status->occupiesSlot()) {
            return ['ok' => false, 'error' => 'Only a new or confirmed booking can be moved.'];
        }

        $duration = (int) ($booking['duration_minutes'] ?? 0);
        if ($duration <= 0) {
            $serviceId = (int) ($booking['service_id'] ?? 0);
            $service = $serviceId > 0 ? $this->services->find($serviceId) : null;
            $duration = $service === null ? $this->availability->gridMinutes() : $this->durationOf($service);
            // fallback for old bookings without stored duration but with multi-service JSON
            if ($duration <= 0 && !empty($booking['service_ids'])) {
                $ids = json_decode((string) $booking['service_ids'], true);
                if (is_array($ids)) {
                    $sum = 0;
                    foreach ($ids as $sid) {
                        $svc = $this->services->find((int) $sid);
                        if ($svc !== null) {
                            $sum += $this->durationOf($svc);
                        }
                    }
                    if ($sum > 0) {
                        $duration = $sum;
                    }
                }
            }
        }

        $start = $time . ':00';
        $end = $this->calculator->endTime($time, $duration) . ':00';

        try {
            $this->db->transaction(function (Database $db) use ($bookingId, $date, $time, $duration, $start, $end): void {
                $this->slots->release($bookingId);

                if (!in_array($time, $this->availability->slotsForDate($date, $duration), true)) {
                    throw new SlotUnavailable('slot taken');
                }

                $cells = $this->calculator->cellsFor($time, $duration, $this->availability->gridMinutes());
                $claims = array_map(static fn (string $cell): string => $date . ' ' . $cell . ':00', $cells);

                if (!$this->slots->claim($bookingId, $claims)) {
                    throw new SlotUnavailable('slot taken');
                }

                $this->bookings->updateSchedule($bookingId, $date, $start, $end);
            });
        } catch (SlotUnavailable) {
            return ['ok' => false, 'error' => 'That time is not available. Please choose another.'];
        }

        $this->bookings->addHistory(
            $bookingId,
            (string) $booking['status'],
            (string) $booking['status'],
            $userId,
            sprintf('Moved from %s %s to %s %s', (string) $booking['booking_date'], substr((string) $booking['start_time'], 0, 5), $date, $time),
        );

        $this->activity->record($userId, 'booking.rescheduled', 'booking', $bookingId, ['to' => $date . ' ' . $time], $ip);

        $fresh = $this->bookings->find($bookingId);

        if ($fresh !== null) {
            $this->notifier?->rescheduled($fresh, $this->describe($booking));
        }

        return ['ok' => true];
    }

    public function saveAdminNotes(int $bookingId, ?string $notes): void
    {
        $this->bookings->updateNotes($bookingId, $notes === '' ? null : $notes);
    }

    /**
     * "Monday 20 October, 10:00–11:00" — the old time, for the "moved" email.
     *
     * @param array<string, mixed> $booking
     */
    private function describe(array $booking): string
    {
        $date = strtotime((string) $booking['booking_date']);

        return sprintf(
            '%s, %s–%s',
            $date === false ? (string) $booking['booking_date'] : date('l j F', $date),
            substr((string) $booking['start_time'], 0, 5),
            substr((string) $booking['end_time'], 0, 5),
        );
    }

    /**
     * @param array<string, mixed> $service
     */
    private function durationOf(array $service): int
    {
        $minutes = (int) ($service['duration_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : $this->availability->gridMinutes();
    }

    /**
     * Customer-facing reference: WZ + date + four characters. Short enough to
     * read down the phone, random enough that it cannot be guessed.
     */
    private function reference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';

            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = 'WZ' . date('ymd') . '-' . $suffix;

            if (!$this->bookings->referenceExists($reference)) {
                return $reference;
            }
        }

        // Practically unreachable; falling back to a longer random string keeps
        // the uniqueness promise rather than looping forever.
        return 'WZ' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function nullable(mixed $value): ?string
    {
        $string = is_scalar($value) ? trim((string) $value) : '';

        return $string === '' ? null : $string;
    }
}
