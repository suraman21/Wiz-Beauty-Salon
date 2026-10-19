<?php
declare(strict_types=1);

namespace Wiz\Core;

use Wiz\Core\Contracts\SessionStore;
use Wiz\Domain\Enums\UserRole;
use Wiz\Repositories\UserRepository;

/**
 * Session-backed authentication.
 *
 * Only the user id is stored in the session; the record is loaded on demand so
 * a deactivated account loses access immediately rather than at next login.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $loaded = false;

    public function __construct(
        private readonly SessionStore $session,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @param array<string, mixed> $user
     */
    public function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, (int) $user['id']);

        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();

        $this->user = null;
        $this->loaded = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }

        $this->loaded = true;
        $id = $this->session->get(self::SESSION_KEY);

        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $record = $this->users->findActiveById((int) $id);

        if ($record === null) {
            $this->session->forget(self::SESSION_KEY);

            return null;
        }

        return $this->user = $record;
    }

    public function role(): ?UserRole
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return UserRole::tryFrom((string) ($user['role'] ?? ''));
    }

    public function isAdmin(): bool
    {
        return $this->role() === UserRole::Admin;
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }
}
