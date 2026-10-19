<?php
declare(strict_types=1);

namespace Wiz\Services;

use DateTimeImmutable;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\SettingsRepository;

/**
 * Answers one question: which times can this service be booked on this date?
 *
 * Everything that could make a day unbookable is gathered here — weekday
 * hours, holiday closures, the booking window, the lead time and the slots
 * already taken — and handed to the calculator as plain data.
 */
final class AvailabilityService
{
    public function __construct(
        private readonly BusinessHoursRepository $hours,
        private readonly ClosureRepository $closures,
        private readonly BookingSlotRepository $slots,
        private readonly SettingsRepository $settings,
        private readonly SlotCalculator $calculator,
    ) {
    }

    public function gridMinutes(): int
    {
        return max(5, $this->settings->int('booking.slot_minutes', 30));
    }

    public function leadMinutes(): int
    {
        return max(0, $this->settings->int('booking.lead_minutes', 120));
    }

    public function maxAdvanceDays(): int
    {
        return max(1, $this->settings->int('booking.max_advance_days', 60));
    }

    /**
     * @return list<string> start times, "HH:MM"
     */
    public function slotsForDate(string $date, int $durationMinutes): array
    {
        $status = $this->dayStatus($date);

        if (!$status['open']) {
            return [];
        }

        $day = $this->parse($date);

        if ($day === null) {
            return [];
        }

        $hours = $this->hours->forDay((int) $day->format('w'));

        if ($hours === null) {
            return [];
        }

        return $this->calculator->slots(
            [
                'opens_at' => $this->stringOrNull($hours['opens_at'] ?? null),
                'closes_at' => $this->stringOrNull($hours['closes_at'] ?? null),
                'is_closed' => (int) ($hours['is_closed'] ?? 1),
            ],
            $durationMinutes,
            $this->gridMinutes(),
            $this->slots->occupiedStarts($date),
            $date === date('Y-m-d') ? $this->earliestStart() : null,
        );
    }

    /**
     * Why a date can or cannot be booked, in words a staff member can read.
     *
     * @return array{open: bool, reason: string|null}
     */
    public function dayStatus(string $date): array
    {
        $day = $this->parse($date);

        if ($day === null) {
            return ['open' => false, 'reason' => 'That date is not valid.'];
        }

        $today = new DateTimeImmutable('today');
        $lastDay = $today->modify('+' . $this->maxAdvanceDays() . ' days');

        if ($day < $today) {
            return ['open' => false, 'reason' => 'That date has already passed.'];
        }

        if ($day > $lastDay) {
            return [
                'open' => false,
                'reason' => sprintf('Bookings open %d days ahead.', $this->maxAdvanceDays()),
            ];
        }

        $closure = $this->closures->covering($date);

        if ($closure !== null) {
            $reason = $this->stringOrNull($closure['reason'] ?? null);

            return [
                'open' => false,
                'reason' => $reason === null ? 'The salon is closed that day.' : 'Closed: ' . $reason,
            ];
        }

        $hours = $this->hours->forDay((int) $day->format('w'));

        if ($hours === null || (int) ($hours['is_closed'] ?? 1) === 1) {
            return ['open' => false, 'reason' => 'The salon is closed on ' . $day->format('l') . 's.'];
        }

        return ['open' => true, 'reason' => null];
    }

    /**
     * A run of consecutive days, ready for the booking calendar.
     *
     * One request instead of fourteen: the site asks once per service, and the
     * whole month of choices arrives with its closed days already marked.
     *
     * @return list<array{date: string, open: bool, reason: string|null, slots: list<string>}>
     */
    public function calendar(string $from, int $days, int $durationMinutes): array
    {
        $start = $this->parse($from) ?? new DateTimeImmutable('today');
        $days = max(1, min(31, $days));
        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
            $status = $this->dayStatus($date);

            $out[] = [
                'date' => $date,
                'open' => $status['open'],
                'reason' => $status['reason'],
                'slots' => $status['open'] ? $this->slotsForDate($date, $durationMinutes) : [],
            ];
        }

        return $out;
    }

    /**
     * First date from $from onwards with at least one free slot.
     */
    public function nextAvailableDate(string $from, int $durationMinutes, int $withinDays = 14): ?string
    {
        $day = $this->parse($from) ?? new DateTimeImmutable('today');

        for ($i = 0; $i < $withinDays; $i++) {
            $date = $day->format('Y-m-d');

            if ($this->slotsForDate($date, $durationMinutes) !== []) {
                return $date;
            }

            $day = $day->modify('+1 day');
        }

        return null;
    }

    /**
     * The earliest clock time that may be booked today, as "HH:MM".
     */
    private function earliestStart(): string
    {
        $now = new DateTimeImmutable('now');
        $minutes = ((int) $now->format('G') * 60) + (int) $now->format('i');
        $earliest = $minutes + $this->leadMinutes();

        return $this->calculator->toTime($earliest);
    }

    private function parse(string $date): ?DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$day instanceof DateTimeImmutable || $day->format('Y-m-d') !== $date) {
            return null;
        }

        return $day;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
