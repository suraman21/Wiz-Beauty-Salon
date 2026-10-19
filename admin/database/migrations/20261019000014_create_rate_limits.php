<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Small fixed-window counter used to keep the public booking endpoint from being
 * hammered. One row per bucket ("booking:203.0.113.9"); the window resets when
 * it expires, so the table stays tiny.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limits (
                bucket       VARCHAR(160) NOT NULL,
                hits         INT UNSIGNED NOT NULL DEFAULT 0,
                window_start DATETIME     NOT NULL,
                PRIMARY KEY (bucket)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS rate_limits');
    }
};
