<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Slot occupancy.
 *
 * A booking of 90 minutes with a 30-minute grid holds three consecutive rows.
 * The primary key on slot_start is what makes "one booking per slot" true at the
 * database level: two people racing for the same time cannot both insert the
 * same row, whatever the application checked a moment earlier. Cancelling or
 * declining a booking deletes its rows, which frees the time automatically.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS booking_slots (
                slot_start DATETIME        NOT NULL,
                booking_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (slot_start),
                KEY booking_slots_booking (booking_id),
                CONSTRAINT booking_slots_booking_fk FOREIGN KEY (booking_id)
                    REFERENCES bookings (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS booking_slots');
    }
};
