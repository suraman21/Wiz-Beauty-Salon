<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Dates the salon is shut regardless of the weekly hours (holidays, training
 * days, family events).
 */
final class ClosureRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT * FROM closures ORDER BY start_date DESC, id DESC',
        );
    }

    /** @return list<array<string, mixed>> */
    public function upcoming(): array
    {
        return $this->db->all(
            'SELECT * FROM closures WHERE end_date >= CURDATE() ORDER BY start_date ASC',
        );
    }

    /**
     * Closures that overlap a range, oldest first.
     *
     * Used by the month calendar so a whole run of days costs one query
     * instead of one per day.
     *
     * @return list<array<string, mixed>>
     */
    public function between(string $from, string $to): array
    {
        return $this->db->all(
            'SELECT * FROM closures
              WHERE end_date >= :from AND start_date <= :to
              ORDER BY start_date ASC, id ASC',
            [':from' => $from, ':to' => $to],
        );
    }

    /** @return array<string, mixed>|null */
    public function covering(string $date): ?array
    {
        return $this->db->first(
            'SELECT * FROM closures
              WHERE :date BETWEEN start_date AND end_date
              ORDER BY start_date ASC
              LIMIT 1',
            [':date' => $date],
        );
    }

    public function create(string $startDate, string $endDate, ?string $reason): int
    {
        return $this->db->insert('closures', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM closures WHERE id = :id', [':id' => $id]);
    }
}
