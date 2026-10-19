<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Customer reviews.
 *
 * Nothing appears on the website until a staff member approves it: reviews are
 * the one piece of content the salon cannot edit after the fact, so they are
 * read before they are published.
 */
final class ReviewRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allWithService(): array
    {
        return $this->db->all(
            'SELECT r.*, s.name AS service_name
               FROM reviews r
          LEFT JOIN services s ON s.id = r.service_id
           ORDER BY r.is_approved ASC, r.created_at DESC, r.id DESC',
        );
    }

    /**
     * What the public site is allowed to show: approved words only, newest
     * first, with the service name attached when it still exists.
     *
     * @return list<array<string, mixed>>
     */
    public function approved(int $limit = 12): array
    {
        return $this->db->all(
            'SELECT r.id, r.customer_name, r.rating, r.body, r.created_at, s.name AS service_name
               FROM reviews r
          LEFT JOIN services s ON s.id = r.service_id
              WHERE r.is_approved = 1
           ORDER BY r.created_at DESC, r.id DESC
              LIMIT ' . max(1, min(50, $limit)),
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM reviews WHERE id = :id', [':id' => $id]);
    }

    public function create(string $customerName, int $rating, string $body, ?int $serviceId, bool $approved): int
    {
        return $this->db->insert('reviews', [
            'customer_name' => $customerName,
            'rating' => max(1, min(5, $rating)),
            'body' => $body,
            'service_id' => $serviceId,
            'is_approved' => $approved ? 1 : 0,
        ]);
    }

    public function setApproved(int $id, bool $approved): void
    {
        $this->db->execute(
            'UPDATE reviews SET is_approved = :approved WHERE id = :id',
            [':id' => $id, ':approved' => $approved ? 1 : 0],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM reviews WHERE id = :id', [':id' => $id]);
    }

    public function countPending(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM reviews WHERE is_approved = 0');
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM reviews');
    }
}
