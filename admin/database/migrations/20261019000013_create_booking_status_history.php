<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS booking_status_history (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id  BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(20)     NULL,
                to_status   VARCHAR(20)     NOT NULL,
                changed_by  BIGINT UNSIGNED NULL,
                note        VARCHAR(255)    NULL,
                created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY booking_history_booking (booking_id, created_at),
                CONSTRAINT booking_history_booking_fk FOREIGN KEY (booking_id)
                    REFERENCES bookings (id) ON DELETE CASCADE,
                CONSTRAINT booking_history_user_fk FOREIGN KEY (changed_by)
                    REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS booking_status_history');
    }
};
