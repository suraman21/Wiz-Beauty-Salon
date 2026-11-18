<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        // Keep backwards compatible: existing bookings keep their single service_id
        // New bookings may have multiple services (service_ids JSON) or be undecided (service_id NULL)
        $db->execute(<<<'SQL'
            ALTER TABLE bookings
                ADD COLUMN service_ids TEXT NULL AFTER service_id,
                ADD COLUMN duration_minutes SMALLINT UNSIGNED NULL AFTER price_cents,
                ADD COLUMN is_undecided TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_minutes
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute(<<<'SQL'
            ALTER TABLE bookings
                DROP COLUMN service_ids,
                DROP COLUMN duration_minutes,
                DROP COLUMN is_undecided
        SQL);
    }
};
