<?php
declare(strict_types=1);

namespace Wiz\Repositories;

/**
 * Services offered by the salon.
 *
 * Images are stored as a bare filename; the public URL is built by the uploader
 * so the storage location can move without a data migration.
 */
final class ServiceRepository extends OrderedRepository
{
    protected function table(): string
    {
        return 'services';
    }

    protected function scopeColumn(): string
    {
        return 'category_id';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithCategory(): array
    {
        return $this->db->all(
            'SELECT s.*, c.name AS category_name
               FROM services s
          LEFT JOIN service_categories c ON c.id = s.category_id
           ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeWithCategory(): array
    {
        return $this->db->all(
            'SELECT s.*, c.name AS category_name
               FROM services s
          LEFT JOIN service_categories c ON c.id = s.category_id
              WHERE s.is_active = 1
           ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC',
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM services WHERE id = :id', [':id' => $id]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM services WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data['sort_order'] = $this->nextSortOrder();

        return $this->db->insert('services', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $assignments = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            $assignments[] = $this->db->quoteIdentifier((string) $column) . ' = :' . $column;
            $params[':' . $column] = $value;
        }

        $this->db->execute(
            'UPDATE services SET ' . implode(', ', $assignments) . ' WHERE id = :id',
            $params,
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute(
            'UPDATE services SET is_active = :active WHERE id = :id',
            [':id' => $id, ':active' => $active ? 1 : 0],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM services WHERE id = :id', [':id' => $id]);
    }

    public function countActive(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM services WHERE is_active = 1');
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM services');
    }
}
