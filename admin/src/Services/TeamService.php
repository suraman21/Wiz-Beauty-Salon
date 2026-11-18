<?php
declare(strict_types=1);

namespace Wiz\Services;

use Wiz\Core\Validator;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\UserRepository;

/**
 * Who may sign in, and with what rights.
 *
 * The rules that matter are not the form fields but the lockout traps: an
 * administrator must not be able to demote or disable themselves, and the last
 * active administrator must always survive. Both are checked here rather than
 * in the controller so they hold no matter which screen calls the service.
 */
final class TeamService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ActivityLogRepository $activity,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error: string|null, id: int|null}
     */
    public function create(array $input, int $actorId, string $ip): array
    {
        $data = $this->validate($input, true);

        if (is_string($data)) {
            return ['ok' => false, 'error' => $data, 'id' => null];
        }

        if ($this->users->findByEmail($data['email']) !== null) {
            return ['ok' => false, 'error' => 'Somebody already signs in with that email address.', 'id' => null];
        }

        $id = $this->users->create(
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
        );

        $this->activity->record($actorId, 'team.created', 'user', $id, ['role' => $data['role']], $ip);

        return ['ok' => true, 'error' => null, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error: string|null, id: int|null}
     */
    public function update(int $id, array $input, int $actorId, string $ip): array
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            return ['ok' => false, 'error' => 'That account no longer exists.', 'id' => null];
        }

        $data = $this->validate($input, false);

        if (is_string($data)) {
            return ['ok' => false, 'error' => $data, 'id' => null];
        }

        $other = $this->users->findByEmail($data['email']);

        if ($other !== null && (int) $other['id'] !== $id) {
            return ['ok' => false, 'error' => 'Somebody already signs in with that email address.', 'id' => null];
        }

        $isSelf = $id === $actorId;
        $wasAdmin = (string) $user['role'] === 'admin';
        $isAdmin = $data['role'] === 'admin';

        if ($isSelf && $wasAdmin && !$isAdmin) {
            return [
                'ok' => false,
                'error' => 'You cannot remove your own administrator access. Ask another administrator to do it.',
                'id' => null,
            ];
        }

        if ($wasAdmin && !$isAdmin && (int) $user['is_active'] === 1 && $this->users->countActiveAdmins() <= 1) {
            return [
                'ok' => false,
                'error' => 'There must always be at least one active administrator.',
                'id' => null,
            ];
        }

        $this->users->updateProfile($id, $data['name'], $data['email'], $data['role']);

        if ($data['password'] !== '') {
            $this->users->updatePassword($id, password_hash($data['password'], PASSWORD_DEFAULT));
        }

        $this->activity->record($actorId, 'team.updated', 'user', $id, [
            'role' => $data['role'],
            'password_reset' => $data['password'] !== '',
        ], $ip);

        return ['ok' => true, 'error' => null, 'id' => $id];
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function setActive(int $id, bool $active, int $actorId, string $ip): array
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            return ['ok' => false, 'error' => 'That account no longer exists.'];
        }

        if (!$active && $id === $actorId) {
            return ['ok' => false, 'error' => 'You cannot switch off your own account.'];
        }

        if (
            !$active
            && (string) $user['role'] === 'admin'
            && (int) $user['is_active'] === 1
            && $this->users->countActiveAdmins() <= 1
        ) {
            return ['ok' => false, 'error' => 'There must always be at least one active administrator.'];
        }

        $this->users->setActive($id, $active);
        $this->activity->record(
            $actorId,
            $active ? 'team.activated' : 'team.deactivated',
            'user',
            $id,
            [],
            $ip,
        );

        return ['ok' => true, 'error' => null];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, email: string, role: string, password: string}|string
     */
    private function validate(array $input, bool $creating): array|string
    {
        $role = (string) ($input['role'] ?? 'staff');

        $rules = [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
        ];

        if ($creating) {
            $rules['password'] = 'required|min:10|max:200';
        } else {
            $rules['password'] = 'min:10|max:200';
        }

        $validator = new Validator($input, $rules, [
            'name' => 'Full name',
            'email' => 'Email address',
            'password' => 'Password',
        ]);

        if (!$validator->validate()) {
            return (string) $validator->firstError();
        }

        if (!in_array($role, ['admin', 'staff'], true)) {
            return 'Choose whether this person is an administrator or staff.';
        }

        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');

        if ($password !== '' && $password !== $confirm) {
            return 'The two passwords do not match.';
        }

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'role' => $role,
            'password' => $password,
        ];
    }
}
