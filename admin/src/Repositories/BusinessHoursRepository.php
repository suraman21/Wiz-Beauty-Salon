<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * The weekly opening hours, one row per weekday (0 = Sunday … 6 = Saturday).
 */
final class BusinessHoursRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM business_hours ORDER BY day_of_week ASC');
    }

    /** @return array<string, mixed>|null */
    public function forDay(int $dayOfWeek): ?array
    {
        return $this->db->first(
            'SELECT * FROM business_hours WHERE day_of_week = :day',
            [':day' => $dayOfWeek],
        );
    }

    public function save(int $dayOfWeek, ?string $opensAt, ?string $closesAt, bool $isClosed): void
    {
        $this->db->execute(
            'INSERT INTO business_hours (day_of_week, opens_at, closes_at, is_closed)
             VALUES (:day, :opens, :closes, :closed)
             ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)',
            [
                ':day' => $dayOfWeek,
                ':opens' => $opensAt,
                ':closes' => $closesAt,
                ':closed' => $isClosed ? 1 : 0,
            ],
        );
    }
}
