<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Support\EthiopianDate;

/**
 * The Ethiopian calendar the panel prints.
 *
 * These vectors are the same ones in the site's suite
 * (src/lib/ethiopian-calendar.test.ts). If the two engines ever disagree, one
 * of the two suites fails — which is the point: the panel and the site must
 * never show a customer two different days.
 */
final class EthiopianDateTest extends TestCase
{
    private const ISO = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        $path = __DIR__ . '/../../../shared/ethiopian-calendar-vectors.json';
        $json = file_get_contents($path);
        self::assertIsString($json, 'the shared vector file must exist');

        /** @var array<string, mixed> $vectors */
        $vectors = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $vectors;
    }

    /**
     * Every conversion both engines are held to. The same file feeds
     * src/lib/ethiopian-calendar.test.ts, so the panel and the site cannot
     * drift apart without one suite going red.
     */
    public function testSharedConversionVectors(): void
    {
        /** @var list<array{gregorian: string, ethiopian: array{year: int, month: int, day: int}, why: string}> $cases */
        $cases = self::vectors()['conversions'];

        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            self::assertSame(
                $case['ethiopian'],
                EthiopianDate::toEthiopian($case['gregorian']),
                'Gregorian → Ethiopian: ' . $case['why'],
            );

            self::assertSame(
                $case['gregorian'],
                EthiopianDate::toGregorian(
                    $case['ethiopian']['year'],
                    $case['ethiopian']['month'],
                    $case['ethiopian']['day'],
                )->format(self::ISO),
                'Ethiopian → Gregorian: ' . $case['why'],
            );
        }
    }

    public function testSharedRoundTripRange(): void
    {
        /** @var array{gregorianFrom: string, gregorianTo: string, ethiopianYearFrom: int, ethiopianYearTo: int} $range */
        $range = self::vectors()['roundTrip'];

        $zone = new \DateTimeZone(EthiopianDate::TIMEZONE);
        $day = new \DateTimeImmutable($range['gregorianFrom'], $zone);
        $end = new \DateTimeImmutable($range['gregorianTo'], $zone);
        $seen = [];

        while ($day <= $end) {
            $ethiopian = EthiopianDate::toEthiopian($day);
            self::assertNotNull($ethiopian, $day->format(self::ISO));
            self::assertTrue(
                EthiopianDate::isValid($ethiopian['year'], $ethiopian['month'], $ethiopian['day']),
                'valid Ethiopian date for ' . $day->format(self::ISO),
            );

            $back = EthiopianDate::toGregorian($ethiopian['year'], $ethiopian['month'], $ethiopian['day']);

            self::assertSame($day->format(self::ISO), $back->format(self::ISO));

            // No Ethiopian day may be reachable from two Gregorian days.
            $key = EthiopianDate::iso($ethiopian['year'], $ethiopian['month'], $ethiopian['day']);
            self::assertArrayNotHasKey($key, $seen);

            $seen[$key] = true;
            $day = $day->modify('+1 day');
        }

        self::assertGreaterThanOrEqual(
            $range['ethiopianYearFrom'],
            min(array_map(static fn (string $iso): int => (int) substr($iso, 0, 4), array_keys($seen))),
        );
        self::assertLessThanOrEqual(
            $range['ethiopianYearTo'],
            max(array_map(static fn (string $iso): int => (int) substr($iso, 0, 4), array_keys($seen))),
        );
    }

    public function testSharedClockVectors(): void
    {
        /** @var list<array{time: string, hour: int, minute: int, period: string, label: string, full: string}> $cases */
        $cases = self::vectors()['clock'];

        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            $clock = EthiopianDate::clock($case['time']);

            self::assertNotNull($clock, 'clock: ' . $case['time']);
            self::assertSame($case['hour'], $clock['hour'], 'hour: ' . $case['time']);
            self::assertSame($case['minute'], $clock['minute'], 'minute: ' . $case['time']);
            self::assertSame($case['period'], $clock['period'], 'period: ' . $case['time']);
            self::assertSame(
                $case['label'] . ' (' . $case['time'] . ')',
                EthiopianDate::formatClock($case['time']),
                'short clock: ' . $case['time'],
            );
            self::assertSame(
                $case['full'] . ' (' . $case['time'] . ')',
                EthiopianDate::formatClock($case['time'], true),
                'full clock: ' . $case['time'],
            );
        }
    }

    public function testSharedFormatVectors(): void
    {
        /** @var list<array{gregorian: string, style: string, expected: string}> $cases */
        $cases = self::vectors()['formats'];

        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            // There is no "gregorian" style in this class; the panel prints the
            // Gregorian alt-text through wire() instead. Same expectation.
            $actual = $case['style'] === 'gregorian'
                ? EthiopianDate::wire($case['gregorian'])['gregorian']
                : EthiopianDate::format($case['gregorian'], $case['style']);

            self::assertSame(
                $case['expected'],
                $actual,
                'format "' . $case['style'] . '" for ' . $case['gregorian'],
            );
        }
    }

    public function testSharedInvalidVectors(): void
    {
        /** @var list<string> $values */
        $values = self::vectors()['invalid'];

        self::assertNotEmpty($values);

        foreach ($values as $value) {
            self::assertNull(EthiopianDate::toEthiopian($value), 'should not read: ' . $value);
        }
    }

    public function testTheTwoHundredYearBoundaryNoLongerDrifts(): void
    {
        // Meskerem 1 2092 EC opens on 12 September 2099 and the year is a full
        // 365 days, so 11 September 2100 is Pagume 5 of 2092 — the day the
        // Gregorian-derived rule lost.
        self::assertSame('2099-09-12', EthiopianDate::newYear(2092)->format(self::ISO));
        self::assertSame('2100-09-11', EthiopianDate::newYear(2093)->modify('-1 day')->format(self::ISO));
        self::assertSame('2100-09-12', EthiopianDate::newYear(2093)->format(self::ISO));
        self::assertSame(['year' => 2092, 'month' => 13, 'day' => 5], EthiopianDate::toEthiopian('2100-09-11'));
        self::assertSame(['year' => 2093, 'month' => 1, 'day' => 1], EthiopianDate::toEthiopian('2100-09-12'));
    }

    public function testPagumeSixExistsOnceEveryFourYearsAndOnlyThen(): void
    {
        for ($year = 2000; $year <= 2200; $year++) {
            $length = (int) EthiopianDate::newYear($year + 1)->diff(EthiopianDate::newYear($year))->days;

            self::assertSame($year % 4 === 3 ? 366 : 365, $length, 'length of ' . $year . ' EC');
            self::assertSame($year % 4 === 3 ? 6 : 5, EthiopianDate::daysInMonth(13, $year), 'Pagume ' . $year);
        }
    }

    public function testMeskeremOneIsElevenSeptember(): void
    {
        self::assertSame(['year' => 2018, 'month' => 1, 'day' => 1], EthiopianDate::toEthiopian('2025-09-11'));
    }

    public function testMeskeremOneIsTwelveSeptemberBeforeAGregorianLeapYear(): void
    {
        // 2000 EC — the Ethiopian millennium — began on 12 September 2007.
        self::assertSame(['year' => 2000, 'month' => 1, 'day' => 1], EthiopianDate::toEthiopian('2007-09-12'));
        self::assertSame(['year' => 2016, 'month' => 1, 'day' => 1], EthiopianDate::toEthiopian('2023-09-12'));
    }

    public function testPagumeRunsToFiveOrSixDays(): void
    {
        self::assertSame(['year' => 2017, 'month' => 13, 'day' => 5], EthiopianDate::toEthiopian('2025-09-10'));
        self::assertSame(['year' => 2018, 'month' => 13, 'day' => 5], EthiopianDate::toEthiopian('2026-09-10'));
        // 2015 EC is a leap year (2015 % 4 === 3): six days, ending 11 Sep 2023.
        self::assertSame(['year' => 2015, 'month' => 13, 'day' => 1], EthiopianDate::toEthiopian('2023-09-06'));
        self::assertSame(['year' => 2015, 'month' => 13, 'day' => 6], EthiopianDate::toEthiopian('2023-09-11'));
        self::assertSame(['year' => 2016, 'month' => 1, 'day' => 1], EthiopianDate::toEthiopian('2023-09-12'));
    }

    public function testAnOrdinaryDayInHidar(): void
    {
        self::assertSame(['year' => 2019, 'month' => 3, 'day' => 8], EthiopianDate::toEthiopian('2026-11-17'));
        self::assertSame(['year' => 2018, 'month' => 4, 'day' => 23], EthiopianDate::toEthiopian('2026-01-01'));
    }

    public function testUnreadableInputIsNullNotADate(): void
    {
        self::assertNull(EthiopianDate::toEthiopian('not a date'));
        self::assertNull(EthiopianDate::toEthiopian(''));
        self::assertNull(EthiopianDate::toEthiopian(null));
        self::assertNull(EthiopianDate::toEthiopian('0000-00-00'));
    }

    public function testRoundTripsEveryDayOfElevenYears(): void
    {
        $cursor = new \DateTimeImmutable('2020-01-01');
        $end = new \DateTimeImmutable('2031-01-01');
        $checked = 0;

        while ($cursor < $end) {
            $ethiopian = EthiopianDate::toEthiopian($cursor);

            self::assertNotNull($ethiopian);
            self::assertSame(
                $cursor->format(self::ISO),
                EthiopianDate::toGregorian($ethiopian['year'], $ethiopian['month'], $ethiopian['day'])->format(self::ISO),
            );

            $cursor = $cursor->modify('+1 day');
            $checked++;
        }

        self::assertGreaterThan(4000, $checked);
    }

    public function testEveryEthiopianYearEndsTheDayBeforeTheNextMeskeremOne(): void
    {
        for ($year = 2000; $year <= 2030; $year++) {
            $last = EthiopianDate::daysInMonth(13, $year);
            $end = EthiopianDate::toGregorian($year, 13, $last);
            $next = EthiopianDate::toGregorian($year + 1, 1, 1);

            self::assertSame($next->format(self::ISO), $end->modify('+1 day')->format(self::ISO));
            self::assertSame($year % 4 === 3 ? 6 : 5, $last);
        }
    }

    public function testValidationRejectsDaysPastTheEndOfTheMonth(): void
    {
        self::assertTrue(EthiopianDate::isValid(2018, 13, 5));
        self::assertTrue(EthiopianDate::isValid(2015, 13, 6));
        self::assertFalse(EthiopianDate::isValid(2018, 13, 6));
        self::assertFalse(EthiopianDate::isValid(2018, 1, 31));
        self::assertFalse(EthiopianDate::isValid(2018, 14, 1));
        self::assertFalse(EthiopianDate::isValid(2018, 0, 1));
        self::assertFalse(EthiopianDate::isValid(2018, 1, 0));
    }

    public function testIsoForm(): void
    {
        self::assertSame('2018-03-08', EthiopianDate::iso(2018, 3, 8));
        self::assertSame(['year' => 2018, 'month' => 3, 'day' => 8], EthiopianDate::parseIso('2018-03-08'));
        self::assertSame(['year' => 2015, 'month' => 13, 'day' => 6], EthiopianDate::parseIso('2015/13/6'));
        self::assertNull(EthiopianDate::parseIso('2016-13-06'));
        self::assertNull(EthiopianDate::parseIso('nonsense'));
    }

    public function testStyles(): void
    {
        self::assertSame('08/03/2019', EthiopianDate::format('2026-11-17', 'short'));
        self::assertSame('Hid 8, 2019', EthiopianDate::format('2026-11-17', 'medium'));
        self::assertSame('Hidar 8, 2019 EC', EthiopianDate::format('2026-11-17', 'long'));
        self::assertSame('ህዳር 8, 2019 ዓ.ም', EthiopianDate::format('2026-11-17', 'amharic'));
        self::assertSame('Hidar 2019', EthiopianDate::format('2026-11-17', 'month-year'));
        // 17 November 2026 is a Tuesday.
        self::assertSame('ማክሰኞ ህዳር 8, 2019 ዓ.ም', EthiopianDate::format('2026-11-17', 'full'));
        self::assertSame('17 Nov 2026', EthiopianDate::formatGregorian('2026-11-17'));
    }

    public function testFormatFallsBackInsteadOfPrintingABrokenDate(): void
    {
        self::assertSame('—', EthiopianDate::format('nope', 'long'));
        self::assertSame('', EthiopianDate::format('nope', 'long', ''));
        self::assertSame('—', EthiopianDate::formatGregorian('nope'));
    }

    public function testTheClockStartsSixHoursEarlier(): void
    {
        self::assertSame('ጠዋት', EthiopianDate::clock('06:00')['period'] ?? null);
        self::assertSame(12, EthiopianDate::clock('06:00')['hour'] ?? null);
        self::assertSame(1, EthiopianDate::clock('07:00')['hour'] ?? null);
        self::assertSame(3, EthiopianDate::clock('09:00')['hour'] ?? null);
        self::assertSame('ከሰዓት', EthiopianDate::clock('12:00')['period'] ?? null);
        self::assertSame(12, EthiopianDate::clock('18:00')['hour'] ?? null);
        self::assertSame(2, EthiopianDate::clock('20:00')['hour'] ?? null);
        self::assertSame('ሌሊት', EthiopianDate::clock('00:00')['period'] ?? null);
        self::assertSame(6, EthiopianDate::clock('00:00')['hour'] ?? null);
        self::assertSame(5, EthiopianDate::clock('23:59')['hour'] ?? null);
    }

    public function testClockLabelsAndRanges(): void
    {
        self::assertSame('3:00 ሰዓት (09:00)', EthiopianDate::formatClock('09:00'));
        self::assertSame('3:00 ሰዓት ጠዋት (09:00)', EthiopianDate::formatClock('09:00', true));
        self::assertSame('7:00 ሰዓት (13:00)', EthiopianDate::formatClock('13:00'));
        self::assertSame('3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)', EthiopianDate::formatClockRange('09:00', '20:00'));
        self::assertSame('8:00 ከሰዓት – 4:00 ማታ (14:00 – 22:00)', EthiopianDate::formatClockRange('14:00', '22:00'));
        // Unreadable input is printed as it came, never swallowed.
        self::assertSame('Closed', EthiopianDate::formatClock('Closed'));
        self::assertSame('nope – 20:00', EthiopianDate::formatClockRange('nope', '20:00'));
        self::assertNull(EthiopianDate::clock('24:00'));
    }

    public function testWireBlockCarriesBothCalendars(): void
    {
        $wire = EthiopianDate::wire('2026-11-17');

        self::assertNotNull($wire);
        self::assertSame('2019-03-08', $wire['iso']);
        self::assertSame('Hidar 8, 2019 EC', $wire['label']);
        self::assertSame('ህዳር 8, 2019 ዓ.ም', $wire['label_am']);
        self::assertSame('17 Nov 2026', $wire['gregorian']);
        self::assertNull(EthiopianDate::wire('nope'));
    }
}
