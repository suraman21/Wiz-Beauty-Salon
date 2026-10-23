<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\UserRepository;
use Wiz\Services\TeamService;

/**
 * The team screen: who can sign in, and what each person may do.
 *
 * Accounts are switched off rather than deleted, so the audit trail keeps
 * pointing at a real person and a returning colleague can be switched back on
 * without a new account.
 */
final class UserController
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly TeamService $team,
        private readonly Auth $auth,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->view->render('admin/users/index', [
            'title' => 'Team',
            'heading' => 'Team',
            'subheading' => 'Who can sign in to this panel, and what they can do.',
            'active' => 'team',
            'users' => $this->users->all(),
            'me' => $this->auth->id(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        return $this->form(null);
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        $user = $this->find((int) ($params['id'] ?? 0));

        return $this->form($user);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->team->create($this->input($request), (int) $this->auth->id(), $request->ip());

        if ($result['ok'] === false) {
            $this->session->flashInput($this->input($request));
            $this->session->flash('error', (string) $result['error']);

            throw new RedirectException('/admin/users/new');
        }

        $this->session->flash('success', 'The account was created. Share the password with them privately.');

        throw new RedirectException('/admin/users');
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $user = $this->find($id);
        $input = $this->input($request);

        $result = $this->team->update($id, $input, (int) $this->auth->id(), $request->ip());

        if ($result['ok'] === false) {
            $this->session->flashInput($input);
            $this->session->flash('error', (string) $result['error']);

            throw new RedirectException('/admin/users/' . $id . '/edit');
        }

        $this->session->flash('success', sprintf('%s was saved.', $user['name']));

        throw new RedirectException('/admin/users');
    }

    /**
     * @param array<string, string> $params
     */
    public function activate(Request $request, array $params = []): Response
    {
        return $this->switchActive((int) ($params['id'] ?? 0), true, $request);
    }

    /**
     * @param array<string, string> $params
     */
    public function deactivate(Request $request, array $params = []): Response
    {
        return $this->switchActive((int) ($params['id'] ?? 0), false, $request);
    }

    private function switchActive(int $id, bool $active, Request $request): Response
    {
        $user = $this->find($id);

        $result = $this->team->setActive($id, $active, (int) $this->auth->id(), $request->ip());

        if ($result['ok'] === false) {
            $this->session->flash('error', (string) $result['error']);

            throw new RedirectException('/admin/users');
        }

        $this->session->flash('success', $active
            ? sprintf('%s can sign in again.', $user['name'])
            : sprintf('%s can no longer sign in.', $user['name']));

        throw new RedirectException('/admin/users');
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        $user = $id > 0 ? $this->users->findById($id) : null;

        if ($user === null) {
            throw HttpException::notFound('That account no longer exists.');
        }

        return $user;
    }

    /**
     * The password is deliberately left out of the flashed form state: it
     * belongs in the response to that one request, not in the session.
     *
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return [
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'role' => $request->string('role', 'staff'),
            'password' => $request->string('password'),
            'password_confirm' => $request->string('password_confirm'),
        ];
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function form(?array $user): Response
    {
        return $this->view->render('admin/users/form', [
            'title' => $user === null ? 'Add a team member' : 'Edit ' . (string) $user['name'],
            'heading' => $user === null ? 'Add a team member' : 'Edit account',
            'subheading' => $user === null
                ? 'They can sign in as soon as you save.'
                : 'Change their details, rights, or password.',
            'active' => 'team',
            'backHref' => '/admin/users',
            'user' => $user,
            'me' => $this->auth->id(),
        ]);
    }
}
