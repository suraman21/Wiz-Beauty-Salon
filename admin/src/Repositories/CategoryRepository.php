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
     * Every service, in the order staff arranged them.
     *
     * There is one level in the catalogue, so there is nothing to count: what
     * the panel lists is what the customer picks.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        return $this->db->all(
            'SELECT * FROM service_categories ORDER BY sort_order ASC, name ASC',
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
            'SELECT id, name, amharic, slug, description, image_path, show_on_home, sort_order
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

    public function create(
        string $name,
        string $slug,
        ?string $description,
        bool $active,
        ?string $amharic = null,
        bool $showOnHome = false,
    ): int {
        return $this->db->insert('service_categories', [
            'name' => $name,
            'amharic' => $amharic,
            'slug' => $slug,
            'description' => $description,
            'is_active' => $active ? 1 : 0,
            'show_on_home' => $showOnHome ? 1 : 0,
            'sort_order' => $this->nextSortOrder(),
        ]);
    }

    public function update(
        int $id,
        string $name,
        ?string $description,
        bool $active,
        ?string $amharic = null,
        bool $showOnHome = false,
    ): void {
        $this->db->execute(
            'UPDATE service_categories
                SET name = :name, amharic = :amharic, description = :description,
                    is_active = :active, show_on_home = :home
              WHERE id = :id',
            [
                ':id' => $id,
                ':name' => $name,
                ':amharic' => $amharic,
                ':description' => $description,
                ':active' => $active ? 1 : 0,
                ':home' => $showOnHome ? 1 : 0,
            ],
        );
    }

    public function setImage(int $id, string $path): void
    {
        $this->db->execute(
            'UPDATE service_categories SET image_path = :path WHERE id = :id',
            [':path' => $path, ':id' => $id],
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
