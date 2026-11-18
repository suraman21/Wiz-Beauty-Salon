<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS login_attempts (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email         VARCHAR(190)    NOT NULL,
                ip_address    VARCHAR(45)     NOT NULL,
                successful    TINYINT(1)      NOT NULL DEFAULT 0,
                attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY login_attempts_email_time (email, attempted_at),
                KEY login_attempts_ip_time (ip_address, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS login_attempts');
    }
};
