<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS bookings (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reference      VARCHAR(24)     NOT NULL,
                customer_name  VARCHAR(120)    NOT NULL,
                customer_email VARCHAR(190)    NULL,
                customer_phone VARCHAR(40)     NOT NULL,
                service_id     BIGINT UNSIGNED NULL,
                service_name   VARCHAR(160)    NOT NULL,
                price_cents    INT UNSIGNED    NULL,
                booking_date   DATE            NOT NULL,
                start_time     TIME            NOT NULL,
                end_time       TIME            NOT NULL,
                status         ENUM('requested','confirmed','completed','cancelled','declined','no_show')
                               NOT NULL DEFAULT 'requested',
                notes          VARCHAR(500)    NULL,
                admin_notes    VARCHAR(500)    NULL,
                created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY bookings_reference (reference),
                KEY bookings_day (booking_date, start_time),
                KEY bookings_status_day (status, booking_date),
                CONSTRAINT bookings_service_fk FOREIGN KEY (service_id)
                    REFERENCES services (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS bookings');
    }
};
