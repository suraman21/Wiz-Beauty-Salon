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
