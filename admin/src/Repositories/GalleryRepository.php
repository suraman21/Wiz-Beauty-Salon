<?php
declare(strict_types=1);

namespace Wiz\Repositories;

/**
 * Salon gallery.
 */
final class GalleryRepository extends OrderedRepository
{
    protected function table(): string
    {
        return 'gallery_items';
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT g.*, c.name AS category_name, c.slug AS category_slug
               FROM gallery_items g
          LEFT JOIN service_categories c ON c.id = g.category_id
           ORDER BY g.sort_order ASC, g.id ASC',
        );
    }

    /** @return list<array<string, mixed>> */
    public function published(): array
    {
        return $this->db->all(
            'SELECT g.*, c.name AS category_name, c.slug AS category_slug
               FROM gallery_items g
          LEFT JOIN service_categories c ON c.id = g.category_id
              WHERE g.is_published = 1
           ORDER BY g.sort_order ASC, g.id ASC',
        );
    }

    /**
     * The categories that actually have a published photo — the gallery filter
     * offers nothing that would come back empty.
     *
     * @return list<array<string, mixed>>
     */
    public function publishedCategories(): array
    {
        return $this->db->all(
            'SELECT c.id, c.name, c.slug, c.amharic, COUNT(g.id) AS photo_count
               FROM gallery_items g
               JOIN service_categories c ON c.id = g.category_id
              WHERE g.is_published = 1
           GROUP BY c.id, c.name, c.slug, c.amharic
           ORDER BY c.sort_order ASC, c.name ASC',
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM gallery_items WHERE id = :id', [':id' => $id]);
    }

    public function create(
        string $imagePath,
        ?string $title,
        ?string $caption,
        bool $published,
        ?int $categoryId = null,
    ): int {
        return $this->db->insert('gallery_items', [
            'image_path' => $imagePath,
            'category_id' => $categoryId,
            'title' => $title,
            'caption' => $caption,
            'is_published' => $published ? 1 : 0,
            'sort_order' => $this->nextSortOrder(),
        ]);
    }

    public function setCategory(int $id, ?int $categoryId): void
    {
        $this->db->execute(
            'UPDATE gallery_items SET category_id = :category WHERE id = :id',
            [':id' => $id, ':category' => $categoryId],
        );
    }

    public function setPublished(int $id, bool $published): void
    {
        $this->db->execute(
            'UPDATE gallery_items SET is_published = :published WHERE id = :id',
            [':id' => $id, ':published' => $published ? 1 : 0],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM gallery_items WHERE id = :id', [':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM gallery_items');
    }

    public function countPublished(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM gallery_items WHERE is_published = 1');
    }
}
