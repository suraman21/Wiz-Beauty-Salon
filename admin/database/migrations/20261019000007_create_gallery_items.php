<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS gallery_items (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title        VARCHAR(160)    NULL,
                caption      VARCHAR(255)    NULL,
                image_path   VARCHAR(255)    NOT NULL,
                is_published TINYINT(1)      NOT NULL DEFAULT 0,
                sort_order   INT             NOT NULL DEFAULT 0,
                created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY gallery_items_order (sort_order),
                KEY gallery_items_published (is_published, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS gallery_items');
    }
};
