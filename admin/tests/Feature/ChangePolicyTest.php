<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\SettingsRepository;
use Wiz\Support\ChangePolicy;

/**
 * How late a customer may still move or cancel — the one rule that used to be a
 * sentence in three templates.
 *
 * These tests exist because the rule has two halves that must never disagree:
 * the sentence a customer reads, and the check the server performs. A page that
 * says "free until 24 hours before" over a controller that accepts a
 * cancellation eleven minutes before is worse than no promise at all.
 */
final class ChangePolicyTest extends TestCase
{
    private Database $db;

    private SettingsRepository $settings;

    private ChangePolicy $policy;

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
        ]);

        (new Migrations($this->db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        $this->db->execute('DELETE FROM settings WHERE setting_key = :key', [':key' => ChangePolicy::SETTING]);

        $this->settings = new SettingsRepository($this->db);
        $this->policy = new ChangePolicy($this->settings);
    }

    private function setHours(int $hours): void
    {
        $this->db->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :inserted)
             ON DUPLICATE KEY UPDATE setting_value = :updated',
            [
                ':key' => ChangePolicy::SETTING,
                ':inserted' => (string) $hours,
                ':updated' => (string) $hours,
            ],
        );

        $this->settings = new SettingsRepository($this->db);
        $this->policy = new ChangePolicy($this->settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(string $date, string $time = '14:00'): array
    {
        return [
            'booking_date' => $date,
            'start_time' => $time . ':00',
            'status' => 'confirmed',
        ];
    }

    public function testTheSalonGetsADaysNoticeUntilItSaysOtherwise(): void
    {
        self::assertSame(24, $this->policy->hours());
        self::assertTrue($this->policy->hasCutOff());
        self::assertSame(
            'Free to change or cancel up to 24 hours before your appointment.',
            $this->policy->summary(),
        );
    }

    public function testThePanelCanAskForTwoDays(): void
    {
        $this->setHours(48);

        self::assertSame(48, $this->policy->hours());
        self::assertSame('48 hours', $this->policy->hoursLabel());
        self::assertStringContainsString('48 hours', $this->policy->summary());
        self::assertSame('Changes close 48 hours before', $this->policy->chip());
        self::assertSame('the 48-hour change window', $this->policy->windowPhrase());
    }

    public function testZeroMeansNoCutOffAtAll(): void
    {
        $this->setHours(0);

        self::assertFalse($this->policy->hasCutOff());
        self::assertSame('no cut-off', $this->policy->hoursLabel());
        self::assertStringContainsString('at any time', $this->policy->summary());

        // Half an hour before the appointment is still "before".
        $now = new DateTimeImmutable('2026-11-25 13:30:00', new \DateTimeZone('Africa/Addis_Ababa'));
        $state = $this->policy->state($this->booking('2026-11-25'), $now);

        self::assertTrue($state['allowed']);
        self::assertSame(0, $state['hours']);
    }

    public function testAWindowIsOpenBeforeTheDeadlineAndClosedAfterIt(): void
    {
        $this->setHours(24);

        $zone = new \DateTimeZone('Africa/Addis_Ababa');
        $booking = $this->booking('2026-11-25', '14:00');

        // The window closes at 14:00 the day before.
        $before = new DateTimeImmutable('2026-11-24 13:59:00', $zone);
        $after = new DateTimeImmutable('2026-11-24 14:01:00', $zone);

        self::assertTrue($this->policy->state($booking, $before)['allowed']);
        self::assertFalse($this->policy->state($booking, $after)['allowed']);

        $closed = $this->policy->state($booking, $after);

        self::assertTrue($closed['can_manage']);
        self::assertStringContainsString('Please call the salon', $closed['reason']);
        // The deadline is printed in the salon's own calendar, with the
        // Gregorian date beside it, so nobody has to convert in their head.
        self::assertStringContainsString('Hidar', $closed['deadline']);
        self::assertStringContainsString('2026', $closed['deadline']);
    }

    public function testAFinishedBookingIsNotManageableHoweverTheWindowReads(): void
    {
        $this->setHours(0);

        foreach (['cancelled', 'declined', 'completed'] as $status) {
            $booking = ['status' => $status] + $this->booking('2026-12-31');
            $state = $this->policy->state($booking, new DateTimeImmutable('2026-11-17 10:00:00'));

            self::assertFalse($state['allowed'], $status . ' must not be changeable');
            self::assertFalse($state['can_manage']);
            self::assertStringContainsString($status, $state['reason']);
        }
    }

    public function testAnAbsurdValueIsClampedRatherThanTrusted(): void
    {
        $this->setHours(9999);

        // Two weeks: past that it is the booking horizon, not a change window.
        self::assertSame(ChangePolicy::MAX_HOURS, $this->policy->hours());
    }

    public function testABrokenDateIsTreatedAsStartedRatherThanOpen(): void
    {
        $state = $this->policy->state(
            ['booking_date' => 'not-a-date', 'start_time' => '', 'status' => 'confirmed'],
            new DateTimeImmutable('2026-11-17 10:00:00'),
        );

        // Refusing a change is the safe side of a broken row.
        self::assertFalse($state['allowed']);
    }
}
