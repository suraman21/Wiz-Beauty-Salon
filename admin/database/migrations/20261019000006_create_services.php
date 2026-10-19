<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS services (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id       BIGINT UNSIGNED NULL,
                name              VARCHAR(160)    NOT NULL,
                slug              VARCHAR(160)    NOT NULL,
                short_description VARCHAR(255)    NULL,
                description       TEXT            NULL,
                price_cents       INT UNSIGNED    NULL,
                duration_minutes  SMALLINT UNSIGNED NULL,
                image_path        VARCHAR(255)    NULL,
                is_active         TINYINT(1)      NOT NULL DEFAULT 1,
                is_featured       TINYINT(1)      NOT NULL DEFAULT 0,
                sort_order        INT             NOT NULL DEFAULT 0,
                created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY services_slug (slug),
                KEY services_category_order (category_id, sort_order),
                KEY services_active (is_active, sort_order),
                CONSTRAINT services_category_fk FOREIGN KEY (category_id)
                    REFERENCES service_categories (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS services');
    }
};
