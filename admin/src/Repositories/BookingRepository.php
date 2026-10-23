<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Booking rows.
 *
 * Service name and price are copied onto the booking at creation time. A salon
 * that renames "Gel manicure" or raises its price must not rewrite history:
 * what the customer booked, and what they were quoted, stays on the record.
 */
final class BookingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->db->insert('bookings', $data);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM bookings WHERE id = :id', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        return $this->db->first('SELECT * FROM bookings WHERE reference = :ref', [':ref' => $reference]);
    }

    public function referenceExists(string $reference): bool
    {
        return $this->db->first('SELECT 1 FROM bookings WHERE reference = :ref', [':ref' => $reference]) !== null;
    }

    /**
     * List for the admin screen, one page at a time.
     *
     * @param array{status?: string, range?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->where($filters);

        $sql = 'SELECT * FROM bookings';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Ascending by date: the next appointment is the one staff need first.
        $sql .= ' ORDER BY booking_date ASC, start_time ASC';
        $sql .= ' LIMIT ' . max(1, min(200, $limit));
        $sql .= ' OFFSET ' . max(0, $offset);

        return $this->db->all($sql, $params);
    }

    /**
     * How many bookings match the same filters, so the list can be paged.
     *
     * @param array{status?: string, range?: string} $filters
     */
    public function countSearch(array $filters = []): int
    {
        [$where, $params] = $this->where($filters);

        $sql = 'SELECT COUNT(*) FROM bookings';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int) $this->db->scalar($sql, $params);
    }

    /**
     * The WHERE clause shared by the list and its count. One place means the
     * page numbers can never disagree with the rows.
     *
     * @param array{status?: string, range?: string} $filters
     * @return array{list<string>, array<string, mixed>}
     */
    private function where(array $filters): array
    {
        $where = [];
        $params = [];

        $status = $filters['status'] ?? '';

        if ($status !== '' && $status !== 'all') {
            // "open" groups the two states that still need attention.
            if ($status === 'open') {
                $where[] = "status IN ('requested','confirmed')";
            } else {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }
        }

        switch ($filters['range'] ?? 'upcoming') {
            case 'today':
                $where[] = 'booking_date = CURDATE()';
                break;
            case 'past':
                $where[] = 'booking_date < CURDATE()';
                break;
            case 'all':
                break;
            default:
                $where[] = 'booking_date >= CURDATE()';
        }

        return [$where, $params];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forDate(string $date): array
    {
        return $this->db->all(
            'SELECT * FROM bookings WHERE booking_date = :date ORDER BY start_time ASC',
            [':date' => $date],
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->execute(
            'UPDATE bookings SET status = :status WHERE id = :id',
            [':id' => $id, ':status' => $status],
        );
    }

    public function updateSchedule(int $id, string $date, string $start, string $end): void
    {
        $this->db->execute(
            'UPDATE bookings SET booking_date = :date, start_time = :start, end_time = :end WHERE id = :id',
            [':id' => $id, ':date' => $date, ':start' => $start, ':end' => $end],
        );
    }

    public function updateNotes(int $id, ?string $adminNotes): void
    {
        $this->db->execute(
            'UPDATE bookings SET admin_notes = :notes WHERE id = :id',
            [':id' => $id, ':notes' => $adminNotes],
        );
    }

    public function countToday(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM bookings
              WHERE booking_date = CURDATE() AND status IN ('requested','confirmed')",
        );
    }

    public function countAwaitingApproval(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'requested'");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $bookingId): array
    {
        return $this->db->all(
            'SELECT h.*, u.name AS user_name
               FROM booking_status_history h
          LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.booking_id = :id
           ORDER BY h.id ASC',
            [':id' => $bookingId],
        );
    }

    public function addHistory(int $bookingId, ?string $from, string $to, ?int $userId, ?string $note): void
    {
        $this->db->insert('booking_status_history', [
            'booking_id' => $bookingId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Find upcoming bookings that can be recovered by phone or email.
     * Only requested/confirmed and future dates — nothing past or finished.
     * Phone: Ethiopian numbers 09..., +251..., 251... all match by last 9 digits.
     * Email: exact match case-insensitive.
     * Uses SQL pre-filter on suffix to avoid scanning entire table.
     *
     * @return list<array<string, mixed>>
     */
    public function findRecoverableByPhone(string $phone): array
    {
        return $this->findRecoverableByIdentifier($phone);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecoverableByEmail(string $email): array
    {
        return $this->findRecoverableByIdentifier($email);
    }

    /**
     * Unified lookup: phone number OR email address.
     * If identifier contains @ it is treated as email, otherwise as phone.
     *
     * @return list<array<string, mixed>>
     */
    public function findRecoverableByIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return [];
        }

        // Email path: contains @ -> validate and match LOWER(customer_email)
        if (str_contains($identifier, '@')) {
            $email = strtolower(trim($identifier));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [];
            }
            // Direct SQL match — no need to scan all rows
            return $this->db->all(
                "SELECT * FROM bookings
                  WHERE status IN ('requested','confirmed')
                    AND booking_date >= CURDATE()
                    AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                    AND LOWER(customer_email) = :email
                  ORDER BY booking_date ASC, start_time ASC
                  LIMIT 20",
                [':email' => $email],
            );
        }

        // Phone path
        $normalized = $this->normalizePhone($identifier);
        if ($normalized === '') {
            return [];
        }
        $last9 = strlen($normalized) > 9 ? substr($normalized, -9) : $normalized;
        if (strlen($last9) < 7) {
            return [];
        }

        // Pre-filter in SQL: phone ending with last9 (covers 09... and +251... without scanning everything)
        // We use LIKE with suffix — stored phones contain digits, spaces, +, -, parentheses.
        // A suffix search will still hit 09... and +251... because both end with same 9 digits.
        // We also fetch a larger window (100) before PHP normalizes, to handle formatting edge cases.
        $rows = $this->db->all(
            "SELECT * FROM bookings
              WHERE status IN ('requested','confirmed')
                AND booking_date >= CURDATE()
                AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                AND customer_phone LIKE :suffix
              ORDER BY booking_date ASC, start_time ASC
              LIMIT 100",
            [':suffix' => '%' . $last9],
        );

        // Fallback if LIKE returned nothing (e.g. stored as 2519... without leading 0 but with different spacing)
        // broaden to full upcoming set capped at 200 and filter in PHP.
        if ($rows === []) {
            $rows = $this->db->all(
                "SELECT * FROM bookings
                  WHERE status IN ('requested','confirmed')
                    AND booking_date >= CURDATE()
                    AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                  ORDER BY booking_date ASC, start_time ASC
                  LIMIT 200",
            );
        }

        $out = [];
        foreach ($rows as $row) {
            $stored = $this->normalizePhone((string) ($row['customer_phone'] ?? ''));
            $storedLast9 = strlen($stored) > 9 ? substr($stored, -9) : $stored;
            if ($storedLast9 === $last9) {
                $out[] = $row;
                if (count($out) >= 20) {
                    break;
                }
            }
        }

        return $out;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '251')) {
            $digits = '0' . substr($digits, 3);
        }
        return $digits;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
