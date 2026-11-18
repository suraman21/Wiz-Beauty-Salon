<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS contact_messages (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name        VARCHAR(120)    NOT NULL,
                email       VARCHAR(190)    NULL,
                phone       VARCHAR(40)     NULL,
                body        VARCHAR(2000)   NOT NULL,
                is_read     TINYINT(1)      NOT NULL DEFAULT 0,
                ip_address  VARCHAR(45)     NULL,
                created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY contact_messages_unread (is_read, created_at),
                KEY contact_messages_time (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS contact_messages');
    }
};
