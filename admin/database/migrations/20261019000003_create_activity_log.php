<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS activity_log (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id     BIGINT UNSIGNED NULL,
                action      VARCHAR(60)     NOT NULL,
                entity_type VARCHAR(60)     NULL,
                entity_id   BIGINT UNSIGNED NULL,
                meta        JSON            NULL,
                ip_address  VARCHAR(45)     NULL,
                created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY activity_log_user_time (user_id, created_at),
                KEY activity_log_time (created_at),
                CONSTRAINT activity_log_user_fk FOREIGN KEY (user_id)
                    REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS activity_log');
    }
};
