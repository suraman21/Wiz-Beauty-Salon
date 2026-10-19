<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use PDOException;
use Wiz\Core\Database;

/**
 * Occupied grid cells.
 *
 * The table's primary key on slot_start is the real guard against double
 * booking; this class only writes and reads rows.
 */
final class BookingSlotRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Grid cells already taken on a date, as "HH:MM".
     *
     * @return list<string>
     */
    public function occupiedStarts(string $date): array
    {
        $rows = $this->db->all(
            'SELECT DATE_FORMAT(slot_start, "%H:%i") AS slot FROM booking_slots
              WHERE slot_start >= :from AND slot_start < :to
              ORDER BY slot_start ASC',
            [':from' => $date . ' 00:00:00', ':to' => $date . ' 23:59:59'],
        );

        return array_values(array_map(static fn (array $row): string => (string) $row['slot'], $rows));
    }

    /**
     * Claim the cells for a booking. A duplicate key means somebody else took
     * one of them between the availability check and this insert.
     *
     * @param list<string> $slots "Y-m-d H:i:s" values
     */
    public function claim(int $bookingId, array $slots): bool
    {
        try {
            foreach ($slots as $slot) {
                $this->db->execute(
                    'INSERT INTO booking_slots (slot_start, booking_id) VALUES (:slot, :booking)',
                    [':slot' => $slot, ':booking' => $bookingId],
                );
            }
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    public function release(int $bookingId): void
    {
        $this->db->execute('DELETE FROM booking_slots WHERE booking_id = :id', [':id' => $bookingId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forBooking(int $bookingId): array
    {
        return $this->db->all(
            'SELECT slot_start FROM booking_slots WHERE booking_id = :id ORDER BY slot_start ASC',
            [':id' => $bookingId],
        );
    }
}
