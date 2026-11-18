<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Csrf;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly AuthService $auth,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function showLogin(Request $request, array $params = []): Response
    {
        return $this->view->render('admin/auth/login', [
            'title' => 'Sign in',
            'next' => $this->safeNext($request->queryString('next')),
            'error' => $this->session->flash('error'),
        ], 'layouts/auth');
    }

    /**
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params = []): Response
    {
        // Discard anything remembered by an earlier attempt before deciding
        // whether this one needs to be remembered again.
        $this->session->clearOld();

        $email = $request->string('email');
        $password = $request->string('password');
        $next = $this->safeNext($request->string('next'));

        $result = $this->auth->attempt($email, $password, $request->ip());

        if ($result['ok'] === false) {
            $this->session->flashInput(['email' => $email]);
            $this->session->flash('error', (string) $result['error']);

            throw new RedirectException('/admin/login' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
        }

        $this->csrf->rotate();

        throw new RedirectException($next !== '' ? $next : '/admin');
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params = []): Response
    {
        $this->auth->logout($request->ip());

        throw new RedirectException('/admin/login');
    }

    /**
     * Only allow internal admin paths as a post-login destination, so the
     * parameter cannot be used as an open redirect.
     */
    private function safeNext(string $next): string
    {
        if ($next === '' || !str_starts_with($next, '/admin')) {
            return '';
        }

        if (str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '';
        }

        return $next;
    }
}
