<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reviews (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_name VARCHAR(120)    NOT NULL,
                rating        TINYINT UNSIGNED NOT NULL DEFAULT 5,
                body          VARCHAR(1000)   NOT NULL,
                service_id    BIGINT UNSIGNED NULL,
                is_approved   TINYINT(1)      NOT NULL DEFAULT 0,
                created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY reviews_approved (is_approved, created_at),
                KEY reviews_service (service_id),
                CONSTRAINT reviews_service_fk FOREIGN KEY (service_id)
                    REFERENCES services (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS reviews');
    }
};
