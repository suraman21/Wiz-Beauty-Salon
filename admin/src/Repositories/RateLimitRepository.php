<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Fixed-window counters.
 *
 * Deliberately coarse: the point is to make scripted abuse tedious, not to
 * bill anyone for requests.
 */
final class RateLimitRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Count one hit and report whether the caller is still inside the limit.
     */
    public function attempt(string $bucket, int $limit, int $windowMinutes): bool
    {
        $row = $this->db->first(
            'SELECT hits, (window_start < (NOW() - INTERVAL :window MINUTE)) AS is_expired
               FROM rate_limits WHERE bucket = :bucket',
            [':bucket' => $bucket, ':window' => $windowMinutes],
        );

        if ($row === null) {
            $this->db->execute(
                'INSERT INTO rate_limits (bucket, hits, window_start) VALUES (:bucket, 1, NOW())',
                [':bucket' => $bucket],
            );

            return $limit >= 1;
        }

        if ((int) $row['is_expired'] === 1) {
            $this->db->execute(
                'UPDATE rate_limits SET hits = 1, window_start = NOW() WHERE bucket = :bucket',
                [':bucket' => $bucket],
            );

            return $limit >= 1;
        }

        $hits = (int) $row['hits'] + 1;
        $this->db->execute(
            'UPDATE rate_limits SET hits = :hits WHERE bucket = :bucket',
            [':bucket' => $bucket, ':hits' => $hits],
        );

        return $hits <= $limit;
    }

    /**
     * How many attempts are left in the current window, without counting one.
     */
    public function remaining(string $bucket, int $limit, int $windowMinutes): int
    {
        $row = $this->db->first(
            'SELECT hits, (window_start < (NOW() - INTERVAL :window MINUTE)) AS is_expired
               FROM rate_limits WHERE bucket = :bucket',
            [':bucket' => $bucket, ':window' => $windowMinutes],
        );

        if ($row === null || (int) $row['is_expired'] === 1) {
            return $limit;
        }

        return max(0, $limit - (int) $row['hits']);
    }

    /**
     * Housekeeping: drop windows that can no longer matter.
     */
    public function prune(int $days = 7): int
    {
        return $this->db->execute(
            'DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL :days DAY)',
            [':days' => $days],
        );
    }
}
