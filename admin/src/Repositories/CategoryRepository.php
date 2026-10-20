<?php
declare(strict_types=1);

namespace Wiz\Repositories;

/**
 * Service categories ("Hair", "Nails", "Skin"...).
 */
final class CategoryRepository extends OrderedRepository
{
    protected function table(): string
    {
        return 'service_categories';
    }

    /**
     * Every category with the number of services filed under it.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        return $this->db->all(
            'SELECT c.*, COUNT(s.id) AS service_count
               FROM service_categories c
          LEFT JOIN services s ON s.category_id = c.id
           GROUP BY c.id
           ORDER BY c.sort_order ASC, c.name ASC',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        return $this->db->all(
            'SELECT id, name, amharic FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC',
        );
    }

    /**
     * The categories the website shows, in the order staff arranged them.
     *
     * @return list<array<string, mixed>>
     */
    public function published(): array
    {
        return $this->db->all(
            'SELECT id, name, amharic, slug, description, sort_order
               FROM service_categories
              WHERE is_active = 1
           ORDER BY sort_order ASC, name ASC',
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM service_categories WHERE id = :id', [':id' => $id]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM service_categories WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function create(string $name, string $slug, ?string $description, bool $active, ?string $amharic = null): int
    {
        return $this->db->insert('service_categories', [
            'name' => $name,
            'amharic' => $amharic,
            'slug' => $slug,
            'description' => $description,
            'is_active' => $active ? 1 : 0,
            'sort_order' => $this->nextSortOrder(),
        ]);
    }

    public function update(int $id, string $name, ?string $description, bool $active, ?string $amharic = null): void
    {
        $this->db->execute(
            'UPDATE service_categories
                SET name = :name, amharic = :amharic, description = :description, is_active = :active
              WHERE id = :id',
            [
                ':id' => $id,
                ':name' => $name,
                ':amharic' => $amharic,
                ':description' => $description,
                ':active' => $active ? 1 : 0,
            ],
        );
    }

    public function delete(int $id): void
    {
        // services.category_id is ON DELETE SET NULL: removing a category never
        // deletes the services filed under it.
        $this->db->execute('DELETE FROM service_categories WHERE id = :id', [':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM service_categories');
    }
}
