<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS business_hours (
                day_of_week TINYINT UNSIGNED NOT NULL,
                opens_at    TIME             NULL,
                closes_at   TIME             NULL,
                is_closed   TINYINT(1)       NOT NULL DEFAULT 1,
                PRIMARY KEY (day_of_week)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 0 = Sunday … 6 = Saturday. A sensible starting week the owner edits
        // on the schedule screen; Sunday closed.
        $db->execute(<<<'SQL'
            INSERT IGNORE INTO business_hours (day_of_week, opens_at, closes_at, is_closed) VALUES
                (0, NULL, NULL, 1),
                (1, '09:00:00', '19:00:00', 0),
                (2, '09:00:00', '19:00:00', 0),
                (3, '09:00:00', '19:00:00', 0),
                (4, '09:00:00', '19:00:00', 0),
                (5, '09:00:00', '19:00:00', 0),
                (6, '09:00:00', '19:00:00', 0)
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS business_hours');
    }
};
