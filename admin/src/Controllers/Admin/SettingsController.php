<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Contracts\MailTransport;
use Wiz\Core\Auth;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Support\MailSettings;
use Wiz\Support\SalonDetails;

/**
 * Email delivery settings, shaped like Bulbula.et's: the administrator can change
 * the transport, its server, port, encryption and account, and send a test that
 * is honest about what "sent" means.
 *
 * The email password is stored encrypted and is only ever shown as "saved".
 */
final class SettingsController
{
    /** Test messages are capped so one stuck button cannot flood a mailbox. */
    private const TEST_LIMIT = 3;
    private const TEST_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly View $view,
        private readonly MailSettings $mail,
        private readonly SalonDetails $salon,
        private readonly MailTransport $transport,
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
                'driver' => $this->mail->driver(),
                'host' => $this->mail->host(),
                'port' => $this->mail->port(),
                'encryption' => $this->mail->encryption(),
                'username' => $this->mail->username(),
                'from_name' => $this->mail->fromName(),
                'from_email' => $this->mail->fromEmail(),
                'salon_email' => $this->mail->salonEmail(),
                'password_saved' => $this->mail->passwordSaved(),
                'readiness' => $this->mail->readiness(),
            ],
            'test_recipient' => (string) ($user['email'] ?? ''),
            'test_left' => $this->testsLeft(),
            'salon' => $this->salon->all(),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function saveMail(Request $request, array $params = []): Response
    {
        $input = [
            'driver' => $request->string('driver', 'smtp'),
            'host' => $request->string('host'),
            'port' => $request->string('port', '587'),
            'encryption' => $request->string('encryption', 'tls'),
            'username' => $request->string('username'),
            'from_name' => $request->string('from_name'),
            'from_email' => $request->string('from_email'),
            'salon_email' => $request->string('salon_email'),
            'password' => $request->string('password'),
        ];

        $driver = in_array($input['driver'], ['smtp', 'mail'], true) ? $input['driver'] : '';

        if ($driver === '') {
            $this->fail('Choose how email should be sent.');
        }

        $encryption = in_array($input['encryption'], ['tls', 'ssl', 'none'], true) ? $input['encryption'] : '';

        if ($encryption === '') {
            $this->fail('Choose an encryption mode.');
        }

        $port = filter_var($input['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65_535]]);

        if ($port === false) {
            $this->fail('The mail server port must be a number between 1 and 65535.');
        }

        $host = trim($input['host']);

        if ($driver === 'smtp') {
            if ($host === '' || strlen($host) > 253 || str_contains($host, '://') || $host !== $input['host']) {
                $this->fail('Enter the mail server name only, for example mail.example.com.');
            }

            if (filter_var($host, FILTER_VALIDATE_IP) === false
                && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                $this->fail('That mail server name is not valid.');
            }

            if (trim($input['username']) === '') {
                $this->fail('Enter the mailbox username for the mail server.');
            }
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $input['username']) === 1) {
            $this->fail('The mailbox username contains characters that are not allowed.');
        }

        $validator = new Validator(
            [
                'from_name' => $input['from_name'],
                'from_email' => $input['from_email'],
                'salon_email' => $input['salon_email'],
                'password' => $input['password'],
            ],
            [
                'from_name' => 'required|min:2|max:100',
                'from_email' => 'required|email|max:190',
                'salon_email' => 'required|email|max:190',
                'password' => 'min:8|max:4096',
            ],
            [
                'from_name' => 'From name',
                'from_email' => 'From address',
                'salon_email' => 'Notification address',
                'password' => 'Email password',
            ],
        );

        if (!$validator->validate()) {
            $this->fail((string) $validator->firstError());
        }

        $this->mail->save([
            'driver' => $driver,
            'host' => $host,
            'port' => (int) $port,
            'encryption' => $encryption,
            'username' => trim($input['username']),
            'from_name' => trim($input['from_name']),
            'from_email' => trim($input['from_email']),
            'salon_email' => trim($input['salon_email']),
        ]);

        $passwordChanged = false;

        if ($input['password'] !== '') {
            $this->mail->savePassword($input['password']);
            $passwordChanged = true;
        }

        $this->activity->record(
            (int) $this->auth->id(),
            'settings.mail_updated',
            'settings',
            null,
            ['driver' => $driver, 'host' => $host, 'encryption' => $encryption, 'password_changed' => $passwordChanged],
            $request->ip(),
        );

        $this->session->flash('success', 'Email settings saved and in use immediately. Send a test to be sure it works.');

        throw new RedirectException('/admin/settings');
    }

    /**
     * The details the Contact page prints. Anything left blank is published as
     * blank — the site hides the row rather than inventing a number.
     *
     * @param array<string, string> $params
     */
    public function saveSalon(Request $request, array $params = []): Response
    {
        $values = [
            SalonDetails::PHONE => $request->string('phone'),
            SalonDetails::WHATSAPP => $request->string('whatsapp'),
            SalonDetails::EMAIL => $request->string('email'),
            SalonDetails::INSTAGRAM => $request->string('instagram'),
            SalonDetails::TIKTOK => $request->string('tiktok'),
            SalonDetails::ADDRESS => $request->string('address'),
        ];

        $email = trim($values[SalonDetails::EMAIL]);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('Enter a valid email address, or leave it blank.');
        }

        foreach ($values as $value) {
            if (mb_strlen(trim($value)) > 190) {
                $this->fail('One of those details is too long. Keep each under 190 characters.');
            }
        }

        $this->salon->save($values);

        $this->activity->record(
            (int) $this->auth->id(),
            'settings.salon_updated',
            'settings',
            null,
            ['filled' => array_keys(array_filter($values, static fn (string $v): bool => trim($v) !== ''))],
            $request->ip(),
        );

        $this->session->flash('success', 'Salon details saved. The website shows them on the Contact page straight away.');

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
            $this->fail('Your account has no email address to send the test to.');
        }

        if (!$this->limits->attempt('mailtest:' . (int) ($user['id'] ?? 0), self::TEST_LIMIT, self::TEST_WINDOW_MINUTES)) {
            $this->fail(sprintf('That is %d tests in the last hour. Try again later.', self::TEST_LIMIT));
        }

        $reference = strtoupper(bin2hex(random_bytes(4)));
        $text = "WIZ Beauty Salon mail transport acceptance test. Reference: {$reference}.\n\n"
            . "Acceptance by a mail server does not prove inbox delivery. Please check your inbox, "
            . 'the junk folder, and the message authentication headers.';

        $result = $this->transport->send(
            $email,
            'WIZ Beauty Salon mail transport acceptance test',
            '<div style="font-family:Arial,sans-serif;padding:24px">'
            . '<h1 style="color:#9c4a2f">Transport acceptance test</h1>'
            . '<p>' . nl2br(e($text)) . '</p></div>',
            $text,
        );

        $this->activity->record(
            (int) $this->auth->id(),
            'settings.test_email',
            'settings',
            null,
            [
                'accepted_by_transport' => (bool) ($result['ok'] ?? false),
                'transport' => (string) ($result['transport'] ?? 'unknown'),
                'test_reference' => $reference,
            ],
            $request->ip(),
        );

        if (($result['ok'] ?? false) !== true) {
            $this->fail('The mail server did not accept test ' . $reference . ': ' . (string) ($result['error'] ?? 'unknown error'));
        }

        $this->session->flash(
            'success',
            "The mail server accepted test {$reference} for {$email}. Check the inbox and the junk folder — "
            . 'acceptance is not proof of delivery, so also confirm the message arrived.',
        );

        throw new RedirectException('/admin/settings');
    }

    private function fail(string $message): never
    {
        $this->session->flash('error', $message);

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
