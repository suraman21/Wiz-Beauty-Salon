<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Services\AvailabilityService;

/**
 * The month calendar is built in one pass for speed. That optimisation is only
 * allowed to be invisible: every day must answer exactly what the single-day
 * methods answer, including closures and closed weekdays.
 */
final class CalendarBulkTest extends TestCase
{
    private Database $db;

    private AvailabilityService $availability;

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');

        if ($name === false || $name === '') {
            self::markTestSkipped('No database configured (DB_NAME is empty).');
        }

        $this->db = new Database([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('DB_USER') ?: '',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
            'timezone' => (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Addis_Ababa')))->format('P'),
        ]);

        (new Migrations($this->db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        // Every weekday open, so the comparison does not depend on the seed.
        for ($day = 0; $day <= 6; $day++) {
            $this->db->execute(
                'UPDATE business_hours SET opens_at = :opens, closes_at = :closes, is_closed = 0 WHERE day_of_week = :day',
                [':opens' => '09:00:00', ':closes' => '19:00:00', ':day' => $day],
            );
        }

        $this->availability = new AvailabilityService(
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new BookingSlotRepository($this->db),
            new SettingsRepository($this->db),
            new SlotCalculator(),
        );
    }

    public function testEveryDayMatchesTheSingleDayAnswer(): void
    {
        $from = date('Y-m-d', strtotime('+1 day'));
        $calendar = $this->availability->calendar($from, 7, 45);

        self::assertCount(7, $calendar);

        foreach ($calendar as $entry) {
            $status = $this->availability->dayStatus($entry['date']);
            $expected = $status['open']
                ? $this->availability->slotsForDate($entry['date'], 45)
                : [];

            self::assertSame($status['open'], $entry['open'], 'open flag for ' . $entry['date']);
            self::assertSame($status['reason'], $entry['reason'], 'reason for ' . $entry['date']);
            self::assertSame($expected, $entry['slots'], 'slots for ' . $entry['date']);
        }
    }

    public function testAClosureClosesTheSameDayInBothPaths(): void
    {
        $from = date('Y-m-d', strtotime('+1 day'));
        $closedDay = date('Y-m-d', strtotime('+3 days'));

        $this->db->execute('DELETE FROM closures');
        $this->db->execute(
            'INSERT INTO closures (start_date, end_date, reason) VALUES (:start, :end, :reason)',
            [':start' => $closedDay, ':end' => $closedDay, ':reason' => 'Training day'],
        );

        try {
            $calendar = $this->availability->calendar($from, 7, 30);
            $entry = null;

            foreach ($calendar as $candidate) {
                if ($candidate['date'] === $closedDay) {
                    $entry = $candidate;
                }
            }

            self::assertNotNull($entry);
            self::assertFalse($entry['open']);
            self::assertSame('Closed: Training day', $entry['reason']);
            self::assertSame([], $entry['slots']);
            self::assertSame(['open' => false, 'reason' => 'Closed: Training day'], $this->availability->dayStatus($closedDay));
        } finally {
            $this->db->execute('DELETE FROM closures');
        }
    }

    public function testTheWindowIsCappedAtThirtyOneDays(): void
    {
        self::assertCount(31, $this->availability->calendar(date('Y-m-d'), 90, 30));
        self::assertCount(1, $this->availability->calendar(date('Y-m-d'), 0, 30));
    }
}
