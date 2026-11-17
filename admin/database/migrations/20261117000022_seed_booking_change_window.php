<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * The change window becomes a saved setting, not a sentence in a template.
 *
 * Until now the site promised "free to change or cancel up to 24 hours before"
 * in three different files, and refused nothing: a customer could cancel
 * eleven minutes before the appointment and the booking service would accept
 * it. The salon had no way to ask for more notice either — a bridal morning
 * needs more than a day.
 *
 * The row written here is the same default the code falls back to, so an
 * existing installation behaves exactly as before on the day this runs, and
 * changes only when someone chooses to change it.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('booking.change_window_hours', '24', NOW())
            ON DUPLICATE KEY UPDATE setting_value = setting_value
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute(<<<'SQL'
            DELETE FROM settings WHERE setting_key = 'booking.change_window_hours'
        SQL);
    }
};
