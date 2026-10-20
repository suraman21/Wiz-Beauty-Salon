<?php
declare(strict_types=1);

namespace Wiz\Support;

use Wiz\Core\Database;

/**
 * Is this uploaded file still needed?
 *
 * The same file can be referenced from more than one place: the seven gallery
 * photos the site shipped with are also the category illustrations, and some of
 * them are the hero and portrait slots. Deleting a gallery photo used to delete
 * the file outright, which would have left a blank category and a missing hero.
 * Every delete and every replacement asks this class first.
 */
final class MediaUsage
{
    /** table => the column that holds the file name. */
    private const TABLES = [
        'services' => 'image_path',
        'service_categories' => 'image_path',
        'gallery_items' => 'image_path',
        'site_images' => 'image_path',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * True when a row other than the one being changed still points at $path.
     *
     * @param string $exceptTable the table whose row is about to lose the file
     * @param int    $exceptId    that row's id
     */
    public function usedElsewhere(string $path, string $exceptTable = '', int $exceptId = 0): bool
    {
        $path = trim($path);

        if ($path === '') {
            return false;
        }

        foreach (self::TABLES as $table => $column) {
            $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = :path', $table, $column);
            $params = [':path' => $path];

            if ($table === $exceptTable && $exceptId > 0) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $exceptId;
            }

            if ((int) $this->db->scalar($sql, $params) > 0) {
                return true;
            }
        }

        return false;
    }

    /** How many rows point at the file, from every table. */
    public function references(string $path): int
    {
        $total = 0;

        foreach (self::TABLES as $table => $column) {
            $total += (int) $this->db->scalar(
                sprintf('SELECT COUNT(*) FROM %s WHERE %s = :path', $table, $column),
                [':path' => trim($path)],
            );
        }

        return $total;
    }
}
