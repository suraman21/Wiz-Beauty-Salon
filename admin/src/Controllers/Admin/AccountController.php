<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\UserRepository;
use Wiz\Services\AuthService;

/**
 * "My account" — the page every signed-in person owns.
 *
 * Until now a password could only be set by an administrator on the Team
 * screen. Staff could not change their own, and nobody could change their name
 * without an administrator doing it for them. Both are basic expectations of a
 * panel, so they live here rather than behind the admin-only screens.
 */
final class AccountController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly AuthService $authService,
        private readonly ActivityLogRepository $activity,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user() ?? [];

        return $this->view->render('admin/account/index', [
            'title' => 'My account',
            'heading' => 'My account',
            'subheading' => 'Your name, sign-in address and password.',
            'active' => 'account',
            'user' => $user,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateProfile(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();

        $validator = new Validator(
            [
                'name' => $request->string('name'),
                'email' => $request->string('email'),
            ],
            [
                'name' => 'required|max:120',
                'email' => 'required|email|max:190',
            ],
            [
                'name' => 'Your name',
                'email' => 'Email address',
            ],
        );

        if (!$validator->validate()) {
            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException('/admin/account');
        }

        $input = $validator->validated();
        $email = mb_strtolower(trim((string) $input['email']));
        $existing = $this->users->findByEmail($email);

        if ($existing !== null && (int) $existing['id'] !== (int) $user['id']) {
            $this->session->flash('error', 'That email address already belongs to another account.');

            throw new RedirectException('/admin/account');
        }

        $this->users->updateProfile(
            (int) $user['id'],
            (string) $input['name'],
            $email,
            (string) $user['role'],
        );

        $this->activity->record(
            (int) $user['id'],
            'account.profile_updated',
            'user',
            (int) $user['id'],
            [],
            $request->ip(),
        );

        $this->session->flash('success', 'Your details were saved.');

        throw new RedirectException('/admin/account');
    }

    /**
     * @param array<string, string> $params
     */
    public function updatePassword(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();

        $validator = new Validator(
            [
                'current_password' => $request->string('current_password'),
                'password' => $request->string('password'),
                'password_confirm' => $request->string('password_confirm'),
            ],
            [
                'current_password' => 'required',
                'password' => 'required|min:10|max:200',
                'password_confirm' => 'required',
            ],
            [
                'current_password' => 'Current password',
                'password' => 'New password',
                'password_confirm' => 'Confirmation',
            ],
        );

        if (!$validator->validate()) {
            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException('/admin/account');
        }

        $input = $validator->validated();

        if ((string) $input['password'] !== (string) $input['password_confirm']) {
            $this->session->flash('error', 'The two new passwords do not match.');

            throw new RedirectException('/admin/account');
        }

        $result = $this->authService->changePassword(
            (int) $user['id'],
            (string) $input['current_password'],
            (string) $input['password'],
            $request->ip(),
        );

        if ($result['ok'] !== true) {
            $this->session->flash('error', (string) $result['error']);

            throw new RedirectException('/admin/account');
        }

        // A new password invalidates any session that was open with the old one.
        $this->session->regenerate();
        $this->session->flash('success', 'Your password was changed.');

        throw new RedirectException('/admin/account');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(): array
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }
}
