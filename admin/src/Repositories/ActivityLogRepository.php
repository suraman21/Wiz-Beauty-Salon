<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Audit trail: who changed what, and when.
 */
final class ActivityLogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function record(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $meta = [],
        string $ip = '',
    ): void {
        $this->db->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES),
            'ip_address' => $ip,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->paginate($limit, 0);
    }

    /**
     * One page of the audit trail, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function paginate(int $limit, int $offset): array
    {
        return $this->db->all(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at,
                    a.ip_address, u.name AS user_name
               FROM activity_log a
          LEFT JOIN users u ON u.id = a.user_id
           ORDER BY a.id DESC
              LIMIT ' . max(1, min(200, $limit)) . '
             OFFSET ' . max(0, $offset),
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM activity_log');
    }
}
