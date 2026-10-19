<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Domain\Booking\SlotCalculator;

final class SlotCalculatorTest extends TestCase
{
    private SlotCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SlotCalculator();
    }

    /**
     * @return array{opens_at: string, closes_at: string, is_closed: int}
     */
    private function hours(string $opens = '09:00:00', string $closes = '19:00:00'): array
    {
        return ['opens_at' => $opens, 'closes_at' => $closes, 'is_closed' => 0];
    }

    public function testFillsTheDayOnTheGrid(): void
    {
        $slots = $this->calculator->slots($this->hours(), 60, 30);

        self::assertSame('09:00', $slots[0]);
        self::assertSame('18:00', $slots[count($slots) - 1]);
        self::assertCount(19, $slots);
    }

    public function testReturnsNothingOnAClosedDay(): void
    {
        $slots = $this->calculator->slots(
            ['opens_at' => null, 'closes_at' => null, 'is_closed' => 1],
            60,
            30,
        );

        self::assertSame([], $slots);
    }

    public function testReturnsNothingWhenTheServiceDoesNotFit(): void
    {
        $slots = $this->calculator->slots($this->hours('09:00:00', '10:00:00'), 90, 30);

        self::assertSame([], $slots);
    }

    public function testABookingBlocksEveryCellItTouches(): void
    {
        // 60 minutes on a 30-minute grid holds 09:00 and 09:30.
        $slots = $this->calculator->slots($this->hours(), 60, 30, ['09:00', '09:30']);

        self::assertNotContains('09:00', $slots);
        self::assertNotContains('09:30', $slots);
        self::assertContains('10:00', $slots);
    }

    public function testATwoHourServiceAlsoBlocksTheSlotBeforeAnOccupiedCell(): void
    {
        // A 120-minute service starting at 08:30 would need 08:30, 09:00, 09:30,
        // 10:00 — the occupied 10:00 is enough to rule it out.
        $hours = $this->hours('08:30:00', '19:00:00');
        $slots = $this->calculator->slots($hours, 120, 30, ['10:00']);

        self::assertNotContains('08:30', $slots);
        self::assertNotContains('09:00', $slots);
        self::assertContains('10:30', $slots);
    }

    public function testRespectsTheEarliestStart(): void
    {
        $slots = $this->calculator->slots($this->hours(), 60, 30, [], '12:00');

        self::assertSame('12:00', $slots[0]);
        self::assertCount(13, $slots);
    }

    public function testGridIsAtLeastFiveMinutes(): void
    {
        $slots = $this->calculator->slots($this->hours('09:00:00', '10:00:00'), 30, 0);

        self::assertSame('09:00', $slots[0]);
        self::assertSame('09:05', $slots[1]);
    }

    public function testCellsForRoundsPartialCellsUp(): void
    {
        // 45 minutes on a 30-minute grid needs two cells.
        self::assertSame(['10:00', '10:30'], $this->calculator->cellsFor('10:00', 45, 30));
    }

    public function testEndTimeAddsTheDuration(): void
    {
        self::assertSame('10:30', $this->calculator->endTime('09:00', 90));
        self::assertSame('23:59', $this->calculator->endTime('23:00', 120));
    }

    public function testParsesTimesWithAndWithoutSeconds(): void
    {
        self::assertSame(540, $this->calculator->toMinutes('09:00'));
        self::assertSame(540, $this->calculator->toMinutes('09:00:00'));
        self::assertNull($this->calculator->toMinutes('25:00'));
        self::assertNull($this->calculator->toMinutes('nonsense'));
    }
}
