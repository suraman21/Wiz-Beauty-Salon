<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * All SQL for the users table lives here.
 */
final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        return $this->db->first(
            'SELECT id, name, email, role, is_active, last_login_at
               FROM users
              WHERE id = :id AND is_active = 1
              LIMIT 1',
            [':id' => $id],
        );
    }

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->db->first(
            'SELECT id, name, email, role, is_active, password_hash, last_login_at
               FROM users
              WHERE email = :email AND is_active = 1
              LIMIT 1',
            [':email' => mb_strtolower(trim($email))],
        );
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->first(
            'SELECT id, name, email, role, is_active, last_login_at, created_at
               FROM users
              WHERE id = :id
              LIMIT 1',
            [':id' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT id, name, email, role, is_active, last_login_at, created_at
               FROM users
              ORDER BY name ASC',
        );
    }

    public function create(string $name, string $email, string $passwordHash, string $role): int
    {
        return $this->db->insert('users', [
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'role' => $role,
            'is_active' => 1,
        ]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            [':id' => $id],
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [':id' => $id, ':hash' => $passwordHash],
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute(
            'UPDATE users SET is_active = :active WHERE id = :id',
            [':id' => $id, ':active' => $active ? 1 : 0],
        );
    }

    public function exists(): bool
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users') > 0;
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1",
        );
    }
}
