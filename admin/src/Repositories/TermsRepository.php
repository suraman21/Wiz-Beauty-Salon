<?php
declare(strict_types=1);

namespace Wiz\Repositories;

/**
 * The booking terms: sections, and the numbered clauses inside them.
 *
 * Two tables rather than one JSON blob, because the panel edits one clause at a
 * time and the contents list needs a stable anchor per section. `slug` is that
 * anchor — generated once from the English heading, never edited afterwards, so
 * `#window` keeps working after the heading is renamed.
 *
 * Every clause carries both languages. A clause cannot exist in one language
 * only: the site's toggle would show a gap, which is worse than not shipping the
 * clause at all.
 */
final class TermsRepository extends OrderedRepository
{
    protected function table(): string
    {
        return 'terms_sections';
    }

    // ---------------------------------------------------------------- reading

    /**
     * Every section, in the order the salon arranged them.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        return $this->db->all(
            'SELECT id, slug, title_en, title_am, sort_order, updated_at
               FROM terms_sections
           ORDER BY sort_order ASC, id ASC',
        );
    }

    /**
     * Every clause, flat, ordered by section then position. The service groups
     * them, so one query serves the whole document.
     *
     * @return list<array<string, mixed>>
     */
    public function clauses(): array
    {
        return $this->db->all(
            'SELECT id, section_id, sort_order, body_en, body_am, updated_at
               FROM terms_clauses
           ORDER BY section_id ASC, sort_order ASC, id ASC',
        );
    }

    /** @return array<string, mixed>|null */
    public function findSection(int $id): ?array
    {
        return $this->db->first('SELECT * FROM terms_sections WHERE id = :id', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findClause(int $id): ?array
    {
        return $this->db->first('SELECT * FROM terms_clauses WHERE id = :id', [':id' => $id]);
    }

    public function sectionCount(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM terms_sections');
    }

    public function clauseCount(int $sectionId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM terms_clauses WHERE section_id = :id',
            [':id' => $sectionId],
        );
    }

    public function slugExists(string $slug): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM terms_sections WHERE slug = :slug',
            [':slug' => $slug],
        ) > 0;
    }

    // ---------------------------------------------------------------- writing

    public function createSection(string $slug, string $titleEn, string $titleAm): int
    {
        $this->db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug, :en, :am, :order)',
            [
                ':slug' => $slug,
                ':en' => $titleEn,
                ':am' => $titleAm,
                ':order' => $this->nextSortOrder(),
            ],
        );

        return $this->db->lastInsertId();
    }

    public function updateSection(int $id, string $titleEn, string $titleAm): void
    {
        // The slug stays put on purpose: it is the anchor customers may have
        // bookmarked or that an email links to.
        $this->db->execute(
            'UPDATE terms_sections SET title_en = :en, title_am = :am, updated_at = NOW() WHERE id = :id',
            [':en' => $titleEn, ':am' => $titleAm, ':id' => $id],
        );
    }

    public function deleteSection(int $id): void
    {
        // Clauses go with it: ON DELETE CASCADE, stated in the migration.
        $this->db->execute('DELETE FROM terms_sections WHERE id = :id', [':id' => $id]);
    }

    public function createClause(int $sectionId, string $bodyEn, string $bodyAm): int
    {
        $this->db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section, :order, :en, :am)',
            [
                ':section' => $sectionId,
                ':order' => $this->nextClauseOrder($sectionId),
                ':en' => $bodyEn,
                ':am' => $bodyAm,
            ],
        );

        return $this->db->lastInsertId();
    }

    public function updateClause(int $id, string $bodyEn, string $bodyAm): void
    {
        $this->db->execute(
            'UPDATE terms_clauses SET body_en = :en, body_am = :am, updated_at = NOW() WHERE id = :id',
            [':en' => $bodyEn, ':am' => $bodyAm, ':id' => $id],
        );
    }

    public function deleteClause(int $id): void
    {
        $this->db->execute('DELETE FROM terms_clauses WHERE id = :id', [':id' => $id]);
    }

    /**
     * Move a clause up or down inside its own section.
     *
     * The shared move() walks one table, and the terms have two, so this swaps
     * inside `terms_clauses` scoped by `section_id`: clause 2 of section 3 never
     * trades places with a clause from another section.
     */
    public function moveClause(int $id, string $direction): bool
    {
        $clause = $this->findClause($id);

        if ($clause === null) {
            return false;
        }

        $sectionId = (int) $clause['section_id'];
        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';

        $neighbour = $this->db->first(
            "SELECT id, sort_order FROM terms_clauses
              WHERE section_id = :section AND sort_order {$comparison} :order
              ORDER BY sort_order {$order}
              LIMIT 1",
            [':section' => $sectionId, ':order' => (int) $clause['sort_order']],
        );

        if ($neighbour === null) {
            return false;
        }

        $this->db->transaction(function ($db) use ($clause, $neighbour): void {
            $db->execute('UPDATE terms_clauses SET sort_order = :order WHERE id = :id', [
                ':order' => (int) $neighbour['sort_order'],
                ':id' => (int) $clause['id'],
            ]);

            $db->execute('UPDATE terms_clauses SET sort_order = :order WHERE id = :id', [
                ':order' => (int) $clause['sort_order'],
                ':id' => (int) $neighbour['id'],
            ]);
        });

        return true;
    }

    /** The next free position at the end of one section's clauses. */
    private function nextClauseOrder(int $sectionId): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM terms_clauses WHERE section_id = :id',
            [':id' => $sectionId],
        );
    }
}
