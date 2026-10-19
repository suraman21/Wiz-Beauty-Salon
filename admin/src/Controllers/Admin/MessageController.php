<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\MessageRepository;

/**
 * The contact inbox.
 *
 * One screen: everything that arrived, newest first, with unread ones marked.
 * There is no folder tree and no reply editor — the salon replies from their
 * own email or phone, and deletes what has been dealt with.
 */
final class MessageController
{
    public function __construct(
        private readonly View $view,
        private readonly MessageRepository $messages,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $unreadOnly = $request->queryString('filter') === 'unread';

        return $this->view->render('admin/messages/index', [
            'title' => 'Messages',
            'heading' => 'Messages',
            'subheading' => 'Everything sent through the contact form.',
            'active' => 'messages',
            'messages' => $this->messages->all($unreadOnly),
            'unreadOnly' => $unreadOnly,
            'unread' => $this->messages->unreadCount(),
            'total' => $this->messages->count(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        $message = $this->find((int) ($params['id'] ?? 0));

        $this->messages->setRead((int) $message['id'], (int) $message['is_read'] !== 1);

        throw new RedirectException('/admin/messages');
    }

    /**
     * @param array<string, string> $params
     */
    public function readAll(Request $request, array $params = []): Response
    {
        foreach ($this->messages->all(true, 500) as $message) {
            $this->messages->setRead((int) $message['id'], true);
        }

        $this->session->flash('success', 'All messages marked as read.');

        throw new RedirectException('/admin/messages');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $message = $this->find((int) ($params['id'] ?? 0));

        $this->messages->delete((int) $message['id']);
        $this->session->flash('success', 'Message deleted.');

        throw new RedirectException('/admin/messages');
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        $message = $id > 0 ? $this->messages->find($id) : null;

        if ($message === null) {
            throw HttpException::notFound('That message no longer exists.');
        }

        return $message;
    }
}
