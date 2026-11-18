<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\Logger;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\MessageRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\UserRepository;

/**
 * The first screen of the day: who is coming in, what is waiting, and what
 * changed recently.
 *
 * Every number comes from the same repository the list screen uses, so the card
 * and the page it links to can never disagree.
 */
final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly BookingRepository $bookings,
        private readonly MessageRepository $messages,
        private readonly ReviewRepository $reviews,
        private readonly CategoryRepository $categories,
        private readonly ActivityLogRepository $activity,
        private readonly UserRepository $users,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user() ?? [];
        $counts = $this->counts();

        return $this->view->render('admin/dashboard', [
            'title' => 'Dashboard',
            'heading' => 'Dashboard',
            'subheading' => 'Welcome back, ' . (string) ($user['name'] ?? 'there') . '.',
            'active' => 'dashboard',
            'user' => $user,
            'counts' => $counts,
            'attention' => $this->attention($counts),
            'today' => $this->todaySchedule(),
            'team' => $this->team(),
            'activity' => $this->activity->recent(8),
        ]);
    }

    /**
     * @return array<string, int|null>
     */
    private function counts(): array
    {
        return [
            'bookings_today' => $this->safe('bookings today', fn (): int => $this->bookings->countToday()),
            'bookings_pending' => $this->safe('bookings awaiting approval', fn (): int => $this->bookings->countAwaitingApproval()),
            'messages_unread' => $this->safe('unread messages', fn (): int => $this->messages->unreadCount()),
            'reviews_pending' => $this->safe('reviews awaiting approval', fn (): int => $this->reviews->countPending()),
            'services_active' => $this->safe('active services', fn (): int => count($this->categories->active())),
        ];
    }

    /**
     * What genuinely needs a person today, with the place to go and do it.
     *
     * @param array<string, int|null> $counts
     * @return list<array{count: int, label: string, href: string, action: string}>
     */
    private function attention(array $counts): array
    {
        $items = [];

        $pending = $counts['bookings_pending'] ?? 0;

        if ($pending > 0) {
            $items[] = [
                'count' => $pending,
                'label' => $pending === 1 ? 'booking is waiting for a yes' : 'bookings are waiting for a yes',
                'href' => '/admin/bookings?status=requested&range=upcoming',
                'action' => 'Review',
            ];
        }

        $unread = $counts['messages_unread'] ?? 0;

        if ($unread > 0) {
            $items[] = [
                'count' => $unread,
                'label' => $unread === 1 ? 'unread message' : 'unread messages',
                'href' => '/admin/messages?filter=unread',
                'action' => 'Read',
            ];
        }

        $reviews = $counts['reviews_pending'] ?? 0;

        if ($reviews > 0) {
            $items[] = [
                'count' => $reviews,
                'label' => $reviews === 1 ? 'review is waiting for approval' : 'reviews are waiting for approval',
                'href' => '/admin/reviews',
                'action' => 'Approve',
            ];
        }

        return $items;
    }

    /**
     * The next few appointments today, so the answer to "who is next?" is the
     * first thing on the screen.
     *
     * @return list<array<string, mixed>>
     */
    private function todaySchedule(): array
    {
        try {
            return array_slice($this->bookings->forDate(date('Y-m-d')), 0, 6);
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard schedule failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array{total: int, admins: int}
     */
    private function team(): array
    {
        try {
            return [
                'total' => count($this->users->all()),
                'admins' => $this->users->countActiveAdmins(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard team count failed', ['error' => $e->getMessage()]);

            return ['total' => 0, 'admins' => 0];
        }
    }

    /**
     * A dashboard card is a convenience; one broken query must not take the
     * whole page down. It is logged, never silently dropped, and the card shows
     * a dash so the number is never mistaken for a real zero.
     *
     * @param callable(): int $query
     */
    private function safe(string $label, callable $query): ?int
    {
        try {
            return $query();
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard count failed: ' . $label, ['error' => $e->getMessage()]);

            return null;
        }
    }
}
