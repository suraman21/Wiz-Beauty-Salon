<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Notes sent through the contact form.
 *
 * Small enough that there is one screen: a list, a read flag and a delete
 * button. Nothing here is ever shown on the public site.
 */
final class MessageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * One page of the inbox, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function paginate(bool $unreadOnly, int $limit, int $offset): array
    {
        $where = $unreadOnly ? 'WHERE is_read = 0' : '';

        return $this->db->all(
            'SELECT * FROM contact_messages ' . $where
            . ' ORDER BY created_at DESC, id DESC'
            . ' LIMIT ' . max(1, min(100, $limit))
            . ' OFFSET ' . max(0, $offset),
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM contact_messages WHERE id = :id', [':id' => $id]);
    }

    public function create(string $name, ?string $email, ?string $phone, string $body, string $ip): int
    {
        return $this->db->insert('contact_messages', [
            'name' => trim($name),
            'email' => $email,
            'phone' => $phone,
            'body' => trim($body),
            'ip_address' => $ip === '' ? null : $ip,
        ]);
    }

    public function setRead(int $id, bool $read): void
    {
        $this->db->run(
            'UPDATE contact_messages SET is_read = :read WHERE id = :id',
            [':read' => $read ? 1 : 0, ':id' => $id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM contact_messages WHERE id = :id', [':id' => $id]);
    }

    public function unreadCount(): int
    {
        $row = $this->db->first('SELECT COUNT(*) AS total FROM contact_messages WHERE is_read = 0');

        return $row === null ? 0 : (int) $row['total'];
    }

    public function count(bool $unreadOnly = false): int
    {
        $sql = $unreadOnly
            ? 'SELECT COUNT(*) AS total FROM contact_messages WHERE is_read = 0'
            : 'SELECT COUNT(*) AS total FROM contact_messages';

        $row = $this->db->first($sql);

        return $row === null ? 0 : (int) $row['total'];
    }

    /** Mark the whole inbox as read in one statement rather than one per row. */
    public function markAllRead(): int
    {
        return $this->db->execute('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
    }
}
