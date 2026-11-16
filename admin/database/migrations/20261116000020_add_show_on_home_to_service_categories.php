<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Staff decide which categories lead the home page.
 *
 * The home page's "Our services" section used to take whatever the four
 * top-ordered categories happened to be, so changing what the salon leads with
 * meant reordering the whole list on the Services page. This gives the panel an
 * explicit switch per category: tick it and the category appears on the home
 * page, in the same order the arrows set. Untick everything and the site falls
 * back to the first four, so the section is never empty.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            ALTER TABLE service_categories
                ADD COLUMN show_on_home TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path
        SQL);

        // Start the salon off with what the home page already showed: the four
        // top categories, so nothing changes on the site until staff decide.
        $db->execute(<<<'SQL'
            UPDATE service_categories
               SET show_on_home = 1
             ORDER BY sort_order ASC, name ASC
             LIMIT 4
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE service_categories DROP COLUMN show_on_home');
    }
};
