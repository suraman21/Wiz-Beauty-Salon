<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name            VARCHAR(120)    NOT NULL,
                email           VARCHAR(190)    NOT NULL,
                password_hash   VARCHAR(255)    NOT NULL,
                role            ENUM('admin','staff') NOT NULL DEFAULT 'staff',
                is_active       TINYINT(1)      NOT NULL DEFAULT 1,
                last_login_at   DATETIME        NULL,
                created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY users_email_unique (email),
                KEY users_role_active (role, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS users');
    }
};
