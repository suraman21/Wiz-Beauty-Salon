<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * Versioned email designs, cloned from Bulbula.et.
 *
 * `email_template_revisions` is append-only: saving a draft adds a row, and the
 * live design is whichever revision `email_template_active` points at. Rolling
 * back is republishing an older row, so no history is ever overwritten.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS email_template_revisions (
                id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key     VARCHAR(80)  NOT NULL,
                version          INT UNSIGNED NOT NULL,
                subject_template VARCHAR(200) NOT NULL,
                html_template    MEDIUMTEXT   NOT NULL,
                text_template    TEXT         NOT NULL,
                project_json     MEDIUMTEXT   NOT NULL,
                created_by       BIGINT UNSIGNED NOT NULL,
                created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_email_template_revision (template_key, version),
                INDEX idx_email_template_key (template_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS email_template_active (
                template_key VARCHAR(80)     NOT NULL PRIMARY KEY,
                revision_id  BIGINT UNSIGNED NOT NULL,
                CONSTRAINT fk_email_template_revision
                    FOREIGN KEY (revision_id) REFERENCES email_template_revisions (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS email_template_active');
        $db->execute('DROP TABLE IF EXISTS email_template_revisions');
    }
};
