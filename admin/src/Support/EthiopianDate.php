<?php
declare(strict_types=1);

namespace Wiz\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * ============================================================
 * Ethiopian calendar & clock — the panel's one implementation
 * ============================================================
 * Ported from the FKSS platform engine (admin/backend/ethiopian_date.php and
 * admin/js/wbws-calendar.js) so the salon and the school read dates the same
 * way. The arithmetic is deliberately identical to those sources; the site's
 * TypeScript twin (`src/lib/ethiopian-calendar.ts`) carries the same tests, so
 * if one side is ever changed the other side's suite fails.
 *
 * RULES (FKSS ADR-0008, adopted here):
 *   1. STORAGE AND THE WIRE STAY GREGORIAN. Every date column, every booking
 *      payload and every .ics file is Gregorian. Date maths keeps working.
 *   2. WHAT A PERSON READS IS ETHIOPIAN. Derived at render time, here.
 *   3. TIMES ARE SHOWN BOTH WAYS: "3:00 ሰዓት (09:00)".
 *
 * ACCURACY: correct through Gregorian 2099. Gregorian 2100 is not a leap year
 * while the Ethiopian year spanning it is, so a one-day drift starts there;
 * at that point the leap inference for the month length must switch to the
 * true Ethiopian rule (`year % 4 === 3`). FKSS documents the same boundary.
 * ============================================================
 */
final class EthiopianDate
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    /** Index 1 = Meskerem … 13 = Pagume. Index 0 is unused, as in the source. */
    public const MONTHS_AM = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ',
    ];

    public const MONTHS_EN = [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas', 5 => 'Tir', 6 => 'Yekatit',
        7 => 'Megabit', 8 => 'Miyazya', 9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
        13 => 'Pagume',
    ];

    public const MONTHS_SHORT = [
        1 => 'Mes', 2 => 'Tik', 3 => 'Hid', 4 => 'Tah', 5 => 'Tir', 6 => 'Yek',
        7 => 'Meg', 8 => 'Miy', 9 => 'Gin', 10 => 'Sen', 11 => 'Ham', 12 => 'Neh', 13 => 'Pag',
    ];

    /** Sunday first; the weekday itself never shifts between calendars. */
    public const DAYS_AM = ['እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ'];

    private static function zone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    /** Local midnight — this class works in calendar days, never in instants. */
    public static function midnight(DateTimeInterface|string|null $input): ?DateTimeImmutable
    {
        if ($input === null || $input === '' || $input === '0000-00-00' || $input === '0000-00-00 00:00:00') {
            return null;
        }

        if ($input instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $input->format('Y-m-d H:i:s'),
                $input->getTimezone() ?: self::zone(),
            );
        } else {
            try {
                $dt = new DateTimeImmutable($input, self::zone());
            } catch (\Throwable) {
                return null;
            }
        }

        if ($dt === false) {
            return null;
        }

        return $dt->setTime(0, 0);
    }

    public static function isGregorianLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    /** Meskerem 1 is 11 September, or the 12th when the next Gregorian year leaps. */
    public static function meskerem1Day(int $gregorianYear): int
    {
        return self::isGregorianLeap($gregorianYear + 1) ? 12 : 11;
    }

    public static function meskerem1(int $gregorianYear): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%04d-09-%02d', $gregorianYear, self::meskerem1Day($gregorianYear)),
            self::zone(),
        );
    }

    /**
     * @return array{year: int, month: int, day: int}|null null when unreadable,
     *         so callers decide what to show rather than printing "0/0/0".
     */
    public static function toEthiopian(DateTimeInterface|string|null $input): ?array
    {
        $dt = self::midnight($input);

        if ($dt === null) {
            return null;
        }

        $gregorianYear = (int) $dt->format('Y');
        $meskerem = self::meskerem1($gregorianYear);

        if ($dt < $meskerem) {
            $meskerem = self::meskerem1($gregorianYear - 1);
            $year = $gregorianYear - 8;
        } else {
            $year = $gregorianYear - 7;
        }

        $daysSince = (int) $meskerem->diff($dt)->days;
        $month = intdiv($daysSince, 30) + 1;
        $day = ($daysSince % 30) + 1;

        if ($month > 13) {
            $month = 13;
            $day = $daysSince - 360 + 1;
        }

        if ($month < 1) {
            $month = 1;
        }

        if ($day < 1) {
            $day = 1;
        }

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public static function toGregorian(int $year, int $month, int $day): DateTimeImmutable
    {
        return self::meskerem1($year + 7)->modify(
            sprintf('+%d days', ($month - 1) * 30 + ($day - 1)),
        );
    }

    /** Pagume has five days, or six when the Ethiopian year leaves a remainder of 3. */
    public static function daysInMonth(int $month, int $year): int
    {
        if ($month >= 1 && $month <= 12) {
            return 30;
        }

        return $month === 13 ? ($year % 4 === 3 ? 6 : 5) : 30;
    }

    public static function isValid(int $year, int $month, int $day): bool
    {
        if ($year < 1 || $month < 1 || $month > 13 || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($month, $year);
    }

    public static function iso(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * "2018-03-12" (or "2018/3/12") → [year, month, day]; null when not a date.
     *
     * @return array{year: int, month: int, day: int}|null
     */
    public static function parseIso(string $value): ?array
    {
        if (preg_match('/^(\d{1,4})[-\/](\d{1,2})[-\/](\d{1,2})$/', trim($value), $m) !== 1) {
            return null;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];

        return self::isValid($year, $month, $day)
            ? ['year' => $year, 'month' => $month, 'day' => $day]
            : null;
    }

    /**
     * 'short' 08/03/2019 · 'medium' Hid 8, 2019 · 'long' Hidar 8, 2019 EC
     * 'full' ማክሰኞ ህዳር 8, 2019 ዓ.ም · 'amharic' ህዳር 8, 2019 ዓ.ም · 'month-year' Hidar 2019
     */
    public static function format(
        DateTimeInterface|string|null $input,
        string $style = 'medium',
        string $fallback = '—',
    ): string {
        $dt = self::midnight($input);
        $date = self::toEthiopian($dt);

        if ($dt === null || $date === null) {
            return $fallback;
        }

        $year = $date['year'];
        $month = $date['month'];
        $day = $date['day'];
        $weekday = (int) $dt->format('w');

        return match ($style) {
            'short' => sprintf('%02d/%02d/%d', $day, $month, $year),
            'medium' => sprintf('%s %d, %d', self::MONTHS_SHORT[$month], $day, $year),
            'long' => sprintf('%s %d, %d EC', self::MONTHS_EN[$month], $day, $year),
            'full' => sprintf('%s %s %d, %d ዓ.ም', self::DAYS_AM[$weekday], self::MONTHS_AM[$month], $day, $year),
            'amharic' => sprintf('%s %d, %d ዓ.ም', self::MONTHS_AM[$month], $day, $year),
            'month-year' => sprintf('%s %d', self::MONTHS_EN[$month], $year),
            default => sprintf('%s %d, %d', self::MONTHS_SHORT[$month], $day, $year),
        };
    }

    /** The Gregorian cross-check, kept small: "17 Nov 2026". */
    public static function formatGregorian(
        DateTimeInterface|string|null $input,
        string $fallback = '—',
    ): string {
        $dt = self::midnight($input);

        return $dt === null ? $fallback : $dt->format('j M Y');
    }

    // ─────────────────────────── the clock ───────────────────────────

    /**
     * Ethiopian time runs twelve hours from dawn: 6:00 is 12 o'clock, 7:00 is
     * 1:00, noon is 6:00, and 6 in the evening is 12:00 again. The period word
     * is what tells the two twelves apart.
     *
     * NOTE: the FKSS sources convert dates only; the clock is the salon's
     * addition, kept beside the ported arithmetic so both live in one file.
     *
     * @return array{hour: int, minute: int, period: string, label: string, full: string}|null
     */
    public static function clock(string $time): ?array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m) !== 1) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        $raw = ($hour + 6) % 12;
        $ethiopianHour = $raw === 0 ? 12 : $raw;
        $period = match (true) {
            $hour >= 6 && $hour < 12 => 'ጠዋት',
            $hour >= 12 && $hour < 18 => 'ከሰዓት',
            $hour >= 18 => 'ማታ',
            default => 'ሌሊት',
        };

        $label = sprintf('%d:%02d ሰዓት', $ethiopianHour, $minute);

        return [
            'hour' => $ethiopianHour,
            'minute' => $minute,
            'period' => $period,
            'label' => $label,
            'full' => $label . ' ' . $period,
        ];
    }

    /** "3:00 ሰዓት (09:00)"; an unreadable time is printed as it came. */
    public static function formatClock(string $time, bool $withPeriod = false): string
    {
        $clock = self::clock($time);

        if ($clock === null) {
            return $time;
        }

        return sprintf('%s (%s)', $withPeriod ? $clock['full'] : $clock['label'], substr($time, 0, 5));
    }

    /**
     * "3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)".
     *
     * Both ends carry their period word: a bare first end would read as the
     * wrong half of the day, and "ሰዓት" is dropped to keep the line short.
     */
    public static function formatClockRange(string $opensAt, string $closesAt): string
    {
        $from = self::clock($opensAt);
        $to = self::clock($closesAt);

        if ($from === null || $to === null) {
            return $opensAt . ' – ' . $closesAt;
        }

        return sprintf(
            '%d:%02d %s – %d:%02d %s (%s – %s)',
            $from['hour'],
            $from['minute'],
            $from['period'],
            $to['hour'],
            $to['minute'],
            $to['period'],
            substr($opensAt, 0, 5),
            substr($closesAt, 0, 5),
        );
    }

    // ─────────────────────────── wire format ───────────────────────────

    /**
     * The JSON block the API adds beside every Gregorian date, so a client can
     * read the Ethiopian date without carrying its own converter.
     *
     * @return array{year: int, month: int, day: int, iso: string, label: string, label_am: string, gregorian: string}
     */
    public static function wire(DateTimeInterface|string|null $input): ?array
    {
        $date = self::toEthiopian($input);

        if ($date === null) {
            return null;
        }

        return [
            'year' => $date['year'],
            'month' => $date['month'],
            'day' => $date['day'],
            'iso' => self::iso($date['year'], $date['month'], $date['day']),
            'label' => self::format($input, 'long'),
            'label_am' => self::format($input, 'amharic'),
            'gregorian' => self::formatGregorian($input),
        ];
    }
}
