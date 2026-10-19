<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\Mailer;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Support\MailSettings;

/**
 * Panel settings. Today that means email delivery: whether it is on, the name
 * and address a message comes from, where the salon's own notifications land,
 * and a test button that replaces "did it work?" with a real answer.
 *
 * The SMTP server and account name are shown but not editable — they are part
 * of the hosting setup, and a wrong value there stops all mail at once.
 */
final class SettingsController
{
    /** Test messages are capped so one stuck button cannot flood a mailbox. */
    private const TEST_LIMIT = 3;
    private const TEST_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly View $view,
        private readonly MailSettings $mail,
        private readonly Mailer $mailer,
        private readonly RateLimitRepository $limits,
        private readonly ActivityLogRepository $activity,
        private readonly Auth $auth,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user() ?? [];

        return $this->view->render('admin/settings/index', [
            'title' => 'Settings',
            'heading' => 'Settings',
            'subheading' => 'How the panel sends email, and where it comes from.',
            'active' => 'settings',
            'mail' => [
                'enabled' => $this->mail->enabled(),
                'from_name' => $this->mail->fromName(),
                'from_email' => $this->mail->fromEmail(),
                'salon_email' => $this->mail->salonEmail(),
                'password_saved' => $this->mail->passwordSaved(),
                'smtp_username' => $this->mail->smtpUsername(),
                'smtp_host' => $this->mail->smtpHost(),
                'driver' => $this->mailer->driver(),
            ],
            'test_recipient' => (string) ($user['email'] ?? ''),
            'test_left' => $this->testsLeft(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function saveMail(Request $request, array $params = []): Response
    {
        $input = [
            'from_name' => $request->string('from_name'),
            'from_email' => $request->string('from_email'),
            'salon_email' => $request->string('salon_email'),
            'password' => $request->string('password'),
        ];

        $validator = new Validator(
            $input,
            [
                'from_name' => 'required|min:2|max:120',
                'from_email' => 'required|email|max:190',
                'salon_email' => 'required|email|max:190',
                'password' => 'min:6|max:200',
            ],
            [
                'from_name' => 'From name',
                'from_email' => 'From address',
                'salon_email' => 'Notification address',
                'password' => 'Email password',
            ],
        );

        if (!$validator->validate()) {
            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException('/admin/settings');
        }

        $enabled = $request->input('enabled') !== null;

        $this->mail->save($enabled, $input['from_name'], $input['from_email'], $input['salon_email']);

        if ($input['password'] !== '') {
            $this->mail->savePassword($input['password']);
        }

        $this->activity->record(
            (int) $this->auth->id(),
            'settings.mail_updated',
            'settings',
            null,
            ['enabled' => $enabled, 'password_changed' => $input['password'] !== ''],
            $request->ip(),
        );

        $this->session->flash('success', $enabled
            ? 'Email settings saved. Send a test to be sure it works.'
            : 'Saved. While delivery is off, messages are only written to the log.');

        throw new RedirectException('/admin/settings');
    }

    /**
     * @param array<string, string> $params
     */
    public function sendTest(Request $request, array $params = []): Response
    {
        $user = $this->auth->user() ?? [];
        $email = (string) ($user['email'] ?? '');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->session->flash('error', 'Your account has no email address to send the test to.');

            throw new RedirectException('/admin/settings');
        }

        if (!$this->limits->attempt('mailtest:' . (int) ($user['id'] ?? 0), self::TEST_LIMIT, self::TEST_WINDOW_MINUTES)) {
            $this->session->flash(
                'error',
                sprintf(
                    'That is %d tests in the last hour. Try again in a little while.',
                    self::TEST_LIMIT,
                ),
            );

            throw new RedirectException('/admin/settings');
        }

        $result = $this->mailer->send(
            $email,
            'Test message from the WIZ panel',
            "This is a test from the salon admin panel.\n\n"
            . "If you are reading it, email delivery works.\n"
            . 'Sent ' . date('j M Y, H:i') . '.',
        );

        $this->activity->record(
            (int) $this->auth->id(),
            'settings.test_email',
            'settings',
            null,
            ['driver' => $result['driver'], 'ok' => $result['ok']],
            $request->ip(),
        );

        if ($result['ok'] === false) {
            $this->session->flash('error', 'The test did not go out: ' . (string) ($result['error'] ?? 'unknown error'));

            throw new RedirectException('/admin/settings');
        }

        $this->session->flash(
            'success',
            $result['driver'] === 'log'
                ? 'Delivery is off, so the test was written to the mail log instead of being sent.'
                : sprintf('Test sent to %s. It should arrive in a minute.', $email),
        );

        throw new RedirectException('/admin/settings');
    }

    /**
     * How many test messages are left in the current window, so the button can
     * say so before it is pressed.
     */
    private function testsLeft(): int
    {
        $id = (int) $this->auth->id();

        if ($id <= 0) {
            return 0;
        }

        return $this->limits->remaining('mailtest:' . $id, self::TEST_LIMIT, self::TEST_WINDOW_MINUTES);
    }
}
