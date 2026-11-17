<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Logger;
use Wiz\Core\Migrations;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Domain\Enums\BookingStatus;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Repositories\UserRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;

/**
 * The booking rules, against a real database.
 *
 * These are the ones that cost the salon money when they are wrong: two people
 * in the same chair, a cancelled appointment that never frees its time, or a
 * client who was quoted a price that later changed.
 */
final class BookingFlowTest extends TestCase
{
    private Database $db;

    private BookingService $bookings;

    private BookingRepository $repository;

    private ServiceRepository $services;

    private CategoryRepository $categories;

    private AvailabilityService $availability;

    private int $serviceId;

    private string $date;

    /** The staff member the tests record as the person making each change. */
    private int $userId = 0;

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

        $this->db->execute('DELETE FROM booking_status_history');
        $this->db->execute('DELETE FROM booking_slots');
        $this->db->execute('DELETE FROM bookings');
        $this->db->execute('DELETE FROM closures');
        $this->db->execute('DELETE FROM services');
        $this->db->execute('DELETE FROM service_categories');
        $this->db->execute('DELETE FROM rate_limits');

        // Every weekday open 09:00–19:00 so the tests never depend on which day
        // CI happens to run.
        for ($day = 0; $day <= 6; $day++) {
            $this->db->execute(
                'UPDATE business_hours SET opens_at = :opens, closes_at = :closes, is_closed = 0 WHERE day_of_week = :day',
                [':opens' => '09:00:00', ':closes' => '19:00:00', ':day' => $day],
            );
        }

        // The history table points at users, and CI starts with an empty
        // database, so make sure there is somebody to attribute changes to.
        $existing = $this->db->first('SELECT id FROM users ORDER BY id LIMIT 1');

        $this->userId = $existing !== null
            ? (int) $existing['id']
            : (new UserRepository($this->db))->create(
                'Booking Test',
                'booking-test-' . bin2hex(random_bytes(4)) . '@example.test',
                password_hash('not-a-real-password', PASSWORD_DEFAULT),
                'admin',
            );

        $this->services = new ServiceRepository($this->db);
        $this->categories = new CategoryRepository($this->db);
        $this->repository = new BookingRepository($this->db);

        $calculator = new SlotCalculator();
        $availability = new AvailabilityService(
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new BookingSlotRepository($this->db),
            new SettingsRepository($this->db),
            $calculator,
        );

        $this->availability = $availability;

        $this->bookings = new BookingService(
            $this->db,
            $this->repository,
            new BookingSlotRepository($this->db),
            $this->services,
            $availability,
            $calculator,
            new ActivityLogRepository($this->db),
            new Logger(sys_get_temp_dir() . '/wiz-test-logs'),
            null,
            $this->categories,
        );

        $this->serviceId = $this->services->create([
            'category_id' => null,
            'name' => 'Silk press',
            'slug' => 'silk-press',
            'short_description' => null,
            'description' => null,
            'price_cents' => 120000,
            'duration_minutes' => 60,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 0,
        ]);

        $this->date = date('Y-m-d', strtotime('+3 days'));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{ok: bool, id?: int, reference?: string, error?: string}
     */
    private function book(string $time = '10:00', array $overrides = []): array
    {
        return $this->bookings->create(array_merge([
            'customer_name' => 'Hanna Tesfaye',
            'customer_phone' => '0911223344',
            'customer_email' => 'hanna@example.com',
            'service_id' => $this->serviceId,
            'date' => $this->date,
            'time' => $time,
            'notes' => null,
        ], $overrides), $this->userId, '127.0.0.1');
    }

    public function testBooksAFreeSlot(): void
    {
        $result = $this->book();

        self::assertTrue($result['ok']);
        self::assertStringStartsWith('WZ', (string) $result['reference']);

        $booking = $this->repository->find((int) $result['id']);

        self::assertNotNull($booking);
        self::assertSame('requested', $booking['status']);
        self::assertSame('10:00:00', $booking['start_time']);
        self::assertSame('11:00:00', $booking['end_time']);

        // Name and price are copied onto the booking.
        self::assertSame('Silk press', $booking['service_name']);
        self::assertSame(120000, (int) $booking['price_cents']);
    }

    public function testRefusesTheSameSlotTwice(): void
    {
        self::assertTrue($this->book('10:00')['ok']);

        $second = $this->book('10:00');

        self::assertFalse($second['ok']);
        self::assertSame('That time is no longer available. Please choose another.', $second['error']);
        self::assertCount(1, $this->repository->forDate($this->date));
    }

    public function testRefusesATimeThatOverlapsALongerBooking(): void
    {
        // 60 minutes holds 10:00 and 10:30 — a 10:30 start must be refused.
        self::assertTrue($this->book('10:00')['ok']);

        $overlap = $this->book('10:30');

        self::assertFalse($overlap['ok']);
    }

    public function testRefusesANonGridTime(): void
    {
        $result = $this->book('10:07');

        self::assertFalse($result['ok']);
    }

    public function testRefusesPastAndFarFutureDates(): void
    {
        $past = $this->book('10:00', ['date' => date('Y-m-d', strtotime('-1 day'))]);
        $far = $this->book('10:00', ['date' => date('Y-m-d', strtotime('+400 days'))]);

        self::assertFalse($past['ok']);
        self::assertFalse($far['ok']);
    }

    public function testConfirmsAndKeepsTheSlot(): void
    {
        $id = (int) $this->book()['id'];

        self::assertTrue($this->bookings->transition($id, BookingStatus::Confirmed, $this->userId)['ok']);

        self::assertSame('confirmed', $this->repository->find($id)['status']);
        self::assertFalse($this->book('10:00')['ok'], 'a confirmed booking still holds its time');
    }

    public function testCancellingFreesTheTime(): void
    {
        $id = (int) $this->book()['id'];

        self::assertTrue($this->bookings->transition($id, BookingStatus::Cancelled, $this->userId)['ok']);

        $again = $this->book('10:00');

        self::assertTrue($again['ok'], 'the cancelled slot can be booked again');
    }

    public function testRefusesAnImpossibleTransition(): void
    {
        $id = (int) $this->book()['id'];

        $result = $this->bookings->transition($id, BookingStatus::Completed, $this->userId);

        self::assertFalse($result['ok']);
        self::assertSame('requested', $this->repository->find($id)['status']);
    }

    public function testCompletedBookingsAreFinal(): void
    {
        $id = (int) $this->book()['id'];
        $this->bookings->transition($id, BookingStatus::Confirmed, $this->userId);

        self::assertTrue($this->bookings->transition($id, BookingStatus::Completed, $this->userId)['ok']);
        self::assertFalse($this->bookings->transition($id, BookingStatus::Cancelled, $this->userId)['ok']);
    }

    public function testWritesAHistoryEntryForEveryChange(): void
    {
        $id = (int) $this->book()['id'];
        $this->bookings->transition($id, BookingStatus::Confirmed, $this->userId, 'Called to confirm');

        $history = $this->repository->history($id);

        self::assertCount(2, $history);
        self::assertSame('requested', $history[0]['to_status']);
        self::assertSame('confirmed', $history[1]['to_status']);
        self::assertSame('Called to confirm', $history[1]['note']);
    }

    public function testReschedulingMovesTheOccupiedCells(): void
    {
        $id = (int) $this->book('10:00')['id'];

        $result = $this->bookings->reschedule($id, $this->date, '14:00', $this->userId);

        self::assertTrue($result['ok']);
        self::assertSame('14:00:00', $this->repository->find($id)['start_time']);

        self::assertTrue($this->book('10:00')['ok'], 'the old time is free again');
        self::assertFalse($this->book('14:00')['ok'], 'the new time is held');
    }

    public function testReschedulingOntoATakenTimeChangesNothing(): void
    {
        $first = (int) $this->book('10:00')['id'];
        self::assertTrue($this->book('14:00')['ok']);

        $result = $this->bookings->reschedule($first, $this->date, '14:00', $this->userId);

        self::assertFalse($result['ok']);
        self::assertSame('10:00:00', $this->repository->find($first)['start_time'], 'the original slot survives a failed move');

        // And its cells are still claimed: nobody else can take 10:00.
        self::assertFalse($this->book('10:00')['ok']);
    }

    public function testAClosureBlocksBooking(): void
    {
        (new ClosureRepository($this->db))->create($this->date, $this->date, 'Public holiday');

        $result = $this->book('10:00');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Public holiday', (string) $result['error']);
    }

    public function testServicePriceIsSnapshotted(): void
    {
        $id = (int) $this->book()['id'];

        $this->db->execute('UPDATE services SET price_cents = 999999, name = ? WHERE id = ?', ['Renamed', $this->serviceId]);

        $booking = $this->repository->find($id);

        self::assertSame(120000, (int) $booking['price_cents']);
        self::assertSame('Silk press', $booking['service_name']);
    }

    // -----------------------------------------------------------------
    // Several services, one visit
    // -----------------------------------------------------------------

    /** @return array{0: int, 1: int, 2: int} */
    private function threeCategories(): array
    {
        $ids = [];

        foreach (['Manicure', 'Pedicure', 'Make Up'] as $index => $name) {
            $ids[] = $this->categories->create(
                $name,
                'multi-' . $index . '-' . bin2hex(random_bytes(3)),
                null,
                true,
            );
        }

        /** @var array{0: int, 1: int, 2: int} $pair */
        return $ids;
    }

    public function testBooksSeveralServicesAsOneAppointment(): void
    {
        [$hair, $nails, $makeUp] = $this->threeCategories();

        $result = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'customer_email' => 'selam@example.com',
            'category_ids' => $hair . ',' . $nails . ',' . $makeUp,
            'date' => $this->date,
            'time' => '10:00',
            'notes' => null,
        ], $this->userId, '127.0.0.1');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));

        $booking = $this->repository->find((int) $result['id']);
        self::assertNotNull($booking);

        // Three services at the salon's gap each: a 90-minute run at the
        // default 30, whatever the panel is set to.
        $expected = 3 * $this->availability->gridMinutes();

        self::assertSame($expected, (int) $booking['duration_minutes']);
        self::assertSame('Manicure + Pedicure + Make Up', $booking['service_name']);
        self::assertSame([$hair, $nails, $makeUp], json_decode((string) $booking['service_ids'], true));
        self::assertNull($booking['service_id']);
        // 10:00 + 90 minutes
        self::assertSame('11:30:00', $booking['end_time']);
    }

    public function testTheWholeRunHasToFitInOneSlot(): void
    {
        [$a, $b, $c] = $this->threeCategories();

        $result = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'category_ids' => [$a, $b, $c],
            'date' => $this->date,
            'time' => '10:00',
        ], $this->userId, '127.0.0.1');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));

        // The last time the 90-minute run fits is 17:30 (close 19:00).
        $late = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'category_ids' => [$a, $b, $c],
            'date' => $this->date,
            'time' => '17:30',
        ], $this->userId, '127.0.0.1');

        self::assertTrue($late['ok'], (string) ($late['error'] ?? ''));

        $tooLate = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'category_ids' => [$a, $b, $c],
            'date' => $this->date,
            'time' => '18:00',
        ], $this->userId, '127.0.0.1');

        self::assertFalse($tooLate['ok']);
    }

    public function testRefusesASecondServiceThatIsNoLongerOffered(): void
    {
        [$a] = $this->threeCategories();

        $result = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'category_ids' => [$a, 999999],
            'date' => $this->date,
            'time' => '10:00',
        ], $this->userId, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertSame('One of the selected services is not available any more.', $result['error']);
    }

    public function testOneChosenServiceIsTheSameAsTheOldSingleBooking(): void
    {
        [$only] = $this->threeCategories();

        $result = $this->bookings->create([
            'customer_name' => 'Selam Bekele',
            'customer_phone' => '0911223355',
            'category_ids' => [$only],
            'date' => $this->date,
            'time' => '10:00',
        ], $this->userId, '127.0.0.1');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));

        $booking = $this->repository->find((int) $result['id']);
        self::assertNotNull($booking);
        self::assertSame($this->availability->gridMinutes(), (int) $booking['duration_minutes']);
        self::assertNull($booking['service_ids']);
    }

    // -----------------------------------------------------------------
    // The gap is a setting, not a constant
    // -----------------------------------------------------------------

    public function testTheGapBetweenTimesComesFromSettings(): void
    {
        $settings = new SettingsRepository($this->db);

        try {
            $settings->put('booking.slot_minutes', '45');

            // A fresh service so the cached setting is read again.
            $availability = new AvailabilityService(
                new BusinessHoursRepository($this->db),
                new ClosureRepository($this->db),
                new BookingSlotRepository($this->db),
                new SettingsRepository($this->db),
                new SlotCalculator(),
            );

            self::assertSame(45, $availability->gridMinutes());

            $slots = $availability->slotsForDate($this->date, 45);
            self::assertContains('09:00', $slots);
            self::assertContains('09:45', $slots);
            self::assertNotContains('09:30', $slots);
            // Times are counted from opening, not backwards from closing: the
            // 45-minute grid from 09:00 reaches 18:00, and a 45-minute booking
            // at 18:00 still ends before 19:00. The next step, 18:45, does not.
            self::assertSame('18:00', $slots[count($slots) - 1]);
        } finally {
            $settings->put('booking.slot_minutes', '30');
        }
    }
}
