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
        return $this->db->all('SELECT * FROM gallery_items ORDER BY sort_order ASC, id ASC');
    }

    /** @return list<array<string, mixed>> */
    public function published(): array
    {
        return $this->db->all(
            'SELECT * FROM gallery_items WHERE is_published = 1 ORDER BY sort_order ASC, id ASC',
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM gallery_items WHERE id = :id', [':id' => $id]);
    }

    public function create(string $imagePath, ?string $title, ?string $caption, bool $published): int
    {
        return $this->db->insert('gallery_items', [
            'image_path' => $imagePath,
            'title' => $title,
            'caption' => $caption,
            'is_published' => $published ? 1 : 0,
            'sort_order' => $this->nextSortOrder(),
        ]);
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
