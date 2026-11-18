<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * The pictures the website's layout uses.
 *
 * One row per slot ("Home page hero", "Before the change"…). A slot is part of
 * the design and never changes; the file behind it is staff's to replace. That
 * separation is what lets the site read its imagery at runtime without the code
 * knowing which photograph happens to be in use.
 */
final class SiteImageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM site_images ORDER BY sort_order ASC, id ASC');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM site_images WHERE id = :id', [':id' => $id]);
    }

    public function setImage(int $id, string $path, ?string $alt): void
    {
        $this->db->execute(
            'UPDATE site_images SET image_path = :path, alt = :alt WHERE id = :id',
            [':path' => $path, ':alt' => $alt, ':id' => $id],
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM site_images');
    }

    /** How many slots have no picture at all — the site falls back to its bundle. */
    public function countEmpty(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM site_images WHERE image_path IS NULL OR image_path = ''",
        );
    }
}
