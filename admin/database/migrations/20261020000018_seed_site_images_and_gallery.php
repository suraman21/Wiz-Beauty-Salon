<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;
use Wiz\Support\SiteMedia;

/**
 * The website's images, and the gallery it was showing, become the panel's.
 *
 * Until now every picture on the site lived in the front-end bundle: the hero,
 * the portraits, the before-and-after pair, the category illustrations and the
 * seven gallery tiles. Staff could not see them, replace them or remove them.
 * This migration creates the tables that hold them and fills those tables with
 * what the site was already showing.
 *
 * Nothing is overwritten — SiteMedia only fills what is empty, so running this
 * against a salon that has already started using the panel changes nothing.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS site_images (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slot       VARCHAR(60)     NOT NULL,
                label      VARCHAR(160)    NOT NULL,
                image_path VARCHAR(190)    NULL,
                alt        VARCHAR(190)    NULL,
                sort_order INT             NOT NULL DEFAULT 0,
                updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY site_images_slot (slot)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $db->execute(<<<'SQL'
            ALTER TABLE service_categories
                ADD COLUMN image_path VARCHAR(190) NULL AFTER description
        SQL);

        $db->execute(<<<'SQL'
            ALTER TABLE gallery_items
                ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER image_path,
                ADD KEY gallery_items_category (category_id),
                ADD CONSTRAINT gallery_items_category_fk
                    FOREIGN KEY (category_id) REFERENCES service_categories (id)
                    ON DELETE SET NULL
        SQL);

        (new SiteMedia())->install($db);
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE gallery_items DROP FOREIGN KEY gallery_items_category_fk');
        $db->execute('ALTER TABLE gallery_items DROP COLUMN category_id');
        $db->execute('ALTER TABLE service_categories DROP COLUMN image_path');
        $db->execute('DROP TABLE IF EXISTS site_images');
    }
};
