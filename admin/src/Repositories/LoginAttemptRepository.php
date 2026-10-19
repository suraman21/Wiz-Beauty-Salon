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
     * Failed attempts for this email *from this address* within the window.
     *
     * The pair matters: counting by email alone let anyone lock the salon owner
     * out of her own panel by guessing wrong passwords from anywhere. Counting
     * by IP alone would punish a whole office for one person's typos. The pair
     * is what makes "five wrong passwords here" mean what it says.
     */
    public function recentFailures(string $email, string $ip, int $minutes = 15): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*)
               FROM login_attempts
              WHERE successful = 0
                AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
                AND email = :email
                AND ip_address = :ip',
            [
                ':minutes' => $minutes,
                ':email' => mb_strtolower(trim($email)),
                ':ip' => $ip,
            ],
        );
    }

    /**
     * Failed attempts from one address, whatever account was tried.
     *
     * This is the wider net: it stops one computer working through a list of
     * accounts, without letting a stranger lock a particular email out.
     */
    public function recentFailuresForIp(string $ip, int $minutes = 15): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*)
               FROM login_attempts
              WHERE successful = 0
                AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
                AND ip_address = :ip',
            [':minutes' => $minutes, ':ip' => $ip],
        );
    }

    public function clearFor(string $email, string $ip): void
    {
        // Only this person, from this address: a success must not wipe the
        // record of somebody else's failed attempts.
        $this->db->execute(
            'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip',
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
