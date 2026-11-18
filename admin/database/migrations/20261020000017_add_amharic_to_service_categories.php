<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Categories get their own Amharic name.
 *
 * The services page prints each category in English and Amharic, but the panel
 * had nowhere to store the Amharic — the site carried it in its own source
 * file, so a category renamed in the panel still showed the old Amharic line.
 *
 * The original seed filed that Amharic text in `description`, which is the
 * field shown under the category name. This moves Ethiopic-script descriptions
 * across and clears them, so Description means a description again.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            ALTER TABLE service_categories
                ADD COLUMN amharic VARCHAR(120) NULL AFTER name
        SQL);

        $db->execute(<<<'SQL'
            UPDATE service_categories
               SET amharic = description, description = NULL
             WHERE description REGEXP '[\\x{1200}-\\x{137F}]'
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute(<<<'SQL'
            UPDATE service_categories
               SET description = amharic
             WHERE amharic IS NOT NULL AND description IS NULL
        SQL);

        $db->execute('ALTER TABLE service_categories DROP COLUMN amharic');
    }
};
