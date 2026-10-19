<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Records login attempts so repeated failures can be throttled per email and
 * per IP address.
 */
final class LoginAttemptRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function record(string $email, string $ip, bool $successful): void
    {
        $this->db->insert('login_attempts', [
            'email' => mb_strtolower(trim($email)),
            'ip_address' => $ip,
            'successful' => $successful ? 1 : 0,
        ]);
    }

    /**
     * Failed attempts for this email or IP within the window.
     */
    public function recentFailures(string $email, string $ip, int $minutes = 15): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*)
               FROM login_attempts
              WHERE successful = 0
                AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
                AND (email = :email OR ip_address = :ip)',
            [
                ':minutes' => $minutes,
                ':email' => mb_strtolower(trim($email)),
                ':ip' => $ip,
            ],
        );
    }

    public function clearFor(string $email, string $ip): void
    {
        $this->db->execute(
            'DELETE FROM login_attempts WHERE email = :email OR ip_address = :ip',
            [':email' => mb_strtolower(trim($email)), ':ip' => $ip],
        );
    }

    /**
     * Housekeeping: drop attempts older than the retention window.
     */
    public function prune(int $days = 30): int
    {
        return $this->db->execute(
            'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL :days DAY)',
            [':days' => $days],
        );
    }
}
