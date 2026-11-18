<?php
declare(strict_types=1);

namespace Wiz\Services;

use Wiz\Core\Auth;
use Wiz\Core\Logger;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\LoginAttemptRepository;
use Wiz\Repositories\UserRepository;

/**
 * Login rules: credential check, throttling and audit.
 *
 * Throttling is deliberately simple — five failures for one email from one
 * address, and a wider limit per address — because it needs no extra
 * infrastructure and is enough to make online guessing impractical without
 * letting a stranger lock a real account out.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly ActivityLogRepository $activity,
        private readonly Auth $auth,
        private readonly Logger $logger,
        private readonly int $maxAttempts = 5,
        private readonly int $lockoutMinutes = 15,
        private readonly int $maxAttemptsPerIp = 25,
    ) {
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        $tooManyHere = $this->attempts->recentFailures($email, $ip, $this->lockoutMinutes) >= $this->maxAttempts;
        $tooManyFromThisAddress = $this->attempts->recentFailuresForIp($ip, $this->lockoutMinutes) >= $this->maxAttemptsPerIp;

        if ($tooManyHere || $tooManyFromThisAddress) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'Too many failed attempts. Try again in %d minutes.',
                    $this->lockoutMinutes,
                ),
            ];
        }

        $user = $this->users->findActiveByEmail($email);

        // Verify against a dummy hash when the account does not exist so the
        // response time does not reveal whether the email is registered.
        $hash = is_array($user) ? (string) $user['password_hash'] : self::dummyHash();
        $valid = password_verify($password, $hash);

        if (!is_array($user) || !$valid) {
            $this->attempts->record($email, $ip, false);
            $this->logger->warning('Failed login attempt', ['email' => $email, 'ip' => $ip]);

            return ['ok' => false, 'error' => 'Those credentials do not match our records.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->attempts->clearFor($email, $ip);
        $this->attempts->record($email, $ip, true);
        $this->users->touchLastLogin((int) $user['id']);
        $this->auth->login($user);
        $this->activity->record((int) $user['id'], 'auth.login', 'user', (int) $user['id'], [], $ip);

        return ['ok' => true, 'error' => null];
    }

    public function logout(string $ip): void
    {
        $id = $this->auth->id();
        $this->auth->logout();

        if ($id !== null) {
            $this->activity->record($id, 'auth.logout', 'user', $id, [], $ip);
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function changePassword(int $userId, string $current, string $new, string $ip = ''): array
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        $record = $this->users->findActiveByEmail((string) $user['email']);

        if ($record === null || !password_verify($current, (string) $record['password_hash'])) {
            return ['ok' => false, 'error' => 'Your current password is incorrect.'];
        }

        $length = mb_strlen($new);

        if ($length < 10) {
            return ['ok' => false, 'error' => 'Your new password must be at least 10 characters.'];
        }

        if ($length > 200) {
            return ['ok' => false, 'error' => 'Your new password may not be longer than 200 characters.'];
        }

        if ($new === $current) {
            return ['ok' => false, 'error' => 'Your new password must be different from the current one.'];
        }

        $this->users->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        $this->activity->record($userId, 'auth.password_changed', 'user', $userId, [], $ip);
        $this->logger->info('Password changed', ['user_id' => $userId]);

        return ['ok' => true, 'error' => null];
    }

    private static function dummyHash(): string
    {
        // A valid bcrypt hash of a random value, generated once per process.
        static $hash = null;

        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
