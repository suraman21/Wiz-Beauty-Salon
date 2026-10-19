<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\Database;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\UserRepository;

final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Database $db,
        private readonly ActivityLogRepository $activity,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user() ?? [];

        return $this->view->render('admin/dashboard', [
            'title' => 'Dashboard',
            'heading' => 'Dashboard',
            'subheading' => 'Welcome back, ' . (string) ($user['name'] ?? 'there') . '.',
            'user' => $user,
            'counts' => $this->counts(),
            'team' => $this->team(),
            'activity' => $this->activity->recent(8),
        ]);
    }

    /**
     * Counters for the cards. Each is defensive: a table that does not exist yet
     * (because its phase has not shipped) shows a dash instead of crashing.
     *
     * @return array<string, int|null>
     */
    private function counts(): array
    {
        return [
            'bookings_today' => $this->safeCount(
                'SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()',
            ),
            'bookings_pending' => $this->safeCount(
                "SELECT COUNT(*) FROM bookings WHERE status = 'requested'",
            ),
            'messages_unread' => $this->safeCount(
                "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'",
            ),
            'reviews_pending' => $this->safeCount(
                'SELECT COUNT(*) FROM reviews WHERE is_approved = 0',
            ),
            'services_active' => $this->safeCount(
                'SELECT COUNT(*) FROM services WHERE is_active = 1',
            ),
        ];
    }

    /**
     * Team members are real data from day one, so this card is never empty.
     *
     * @return array{total: int, admins: int}
     */
    private function team(): array
    {
        try {
            return [
                'total' => count($this->users->all()),
                'admins' => $this->users->countActiveAdmins(),
            ];
        } catch (\Throwable) {
            return ['total' => 0, 'admins' => 0];
        }
    }

    private function safeCount(string $sql): ?int
    {
        try {
            return (int) $this->db->scalar($sql);
        } catch (\Throwable) {
            return null;
        }
    }
}
