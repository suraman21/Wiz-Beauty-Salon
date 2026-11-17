<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Staff decide which gallery photos lead the home page.
 *
 * The home page's "A visual mood" section used to take three fixed slots from
 * the Website Images screen, so changing what the salon leads with meant
 * editing a different screen from the one staff manage their photos on. This
 * gives the Gallery an explicit switch per photo: tick it and the photo
 * appears in the home page mood section, in the order the arrows set. Untick
 * everything and the site falls back to the Website Images slots, so the
 * section is never empty.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            ALTER TABLE gallery_items
                ADD COLUMN show_on_home TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published
        SQL);

        // Start the salon off with what the home page mood section shows
        // today: the first three published photos, so nothing changes on the
        // site until staff decide otherwise.
        $db->execute(<<<'SQL'
            UPDATE gallery_items
               SET show_on_home = 1
             WHERE is_published = 1
             ORDER BY sort_order ASC, id ASC
             LIMIT 3
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE gallery_items DROP COLUMN show_on_home');
    }
};
