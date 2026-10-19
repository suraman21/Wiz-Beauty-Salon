<?php
declare(strict_types=1);

namespace Wiz\Domain\Booking;

/**
 * Turns a day's opening hours into bookable start times.
 *
 * Pure arithmetic on purpose: no database, no clock, no request. Every rule the
 * salon cares about is in this one function, which makes it possible to test the
 * awkward cases (a 90-minute treatment near closing time, a grid that does not
 * divide the day evenly) without any fixtures.
 */
final class SlotCalculator
{
    public const TIME_FORMAT = 'H:i';

    /**
     * @param array{opens_at: string|null, closes_at: string|null, is_closed: bool|int} $hours
     * @param list<string> $occupied start times already taken, "HH:MM"
     * @param string|null $notBefore earliest start allowed, "HH:MM"
     * @return list<string> start times, "HH:MM"
     */
    public function slots(
        array $hours,
        int $durationMinutes,
        int $gridMinutes,
        array $occupied = [],
        ?string $notBefore = null,
    ): array {
        if ((int) ($hours['is_closed'] ?? 1) === 1) {
            return [];
        }

        $opensAt = $hours['opens_at'] ?? null;
        $closesAt = $hours['closes_at'] ?? null;

        if (!is_string($opensAt) || !is_string($closesAt)) {
            return [];
        }

        $open = $this->toMinutes($opensAt);
        $close = $this->toMinutes($closesAt);

        if ($open === null || $close === null || $close <= $open) {
            return [];
        }

        $grid = max(5, $gridMinutes);
        $duration = max($grid, $durationMinutes);

        // A 45-minute treatment on a 30-minute grid holds two cells: one whole
        // and one partial. Rounding up is what keeps the next booking from
        // starting before the room is free.
        $cells = (int) ceil($duration / $grid);
        $lastStart = $close - ($cells * $grid);

        if ($lastStart < $open) {
            return [];
        }

        $taken = [];

        foreach ($occupied as $start) {
            $minutes = $this->toMinutes($start);

            if ($minutes !== null) {
                $taken[$minutes] = true;
            }
        }

        $floor = $notBefore === null ? null : $this->toMinutes($notBefore);
        $slots = [];

        for ($start = $open; $start <= $lastStart; $start += $grid) {
            if ($floor !== null && $start < $floor) {
                continue;
            }

            $free = true;

            for ($cell = 0; $cell < $cells; $cell++) {
                if (isset($taken[$start + ($cell * $grid)])) {
                    $free = false;
                    break;
                }
            }

            if ($free) {
                $slots[] = $this->toTime($start);
            }
        }

        return $slots;
    }

    /**
     * The grid cells a booking starting at $start would hold, as "HH:MM".
     *
     * @return list<string>
     */
    public function cellsFor(string $start, int $durationMinutes, int $gridMinutes): array
    {
        $from = $this->toMinutes($start);

        if ($from === null) {
            return [];
        }

        $grid = max(5, $gridMinutes);
        $cells = (int) ceil(max($grid, $durationMinutes) / $grid);

        $result = [];

        for ($cell = 0; $cell < $cells; $cell++) {
            $result[] = $this->toTime($from + ($cell * $grid));
        }

        return $result;
    }

    public function endTime(string $start, int $durationMinutes): string
    {
        $from = $this->toMinutes($start) ?? 0;

        return $this->toTime($from + max(5, $durationMinutes));
    }

    public function toMinutes(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m) !== 1) {
            return null;
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    public function toTime(int $minutes): string
    {
        $minutes = max(0, min(1439, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
