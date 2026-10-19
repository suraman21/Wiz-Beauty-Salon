<?php
declare(strict_types=1);

namespace Wiz\Core;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Sends one plain message, or writes it down instead.
 *
 * Three outcomes, chosen by configuration rather than by code changes:
 *
 *   log   — nothing leaves the machine; the message is appended to
 *           storage/logs/mail.log. The default, so a half-configured site can
 *           never spam real customers.
 *   mail  — PHP's mail(), which on cPanel hands the message to the local MTA.
 *   smtp  — PHPMailer, when it is installed and a username is configured.
 *
 * send() never throws. A booking must not fail because the mail server is
 * having a bad morning, so the caller gets a result array it can log.
 */
final class Mailer
{
    /**
     * @param array<string, mixed> $config the mail.php config array
     */
    public function __construct(
        private readonly array $config,
        private readonly string $logDir,
    ) {
    }

    /**
     * @param string|null $replyTo where a reply should go; the customer's own
     *                             address for salon notifications, so staff can
     *                             simply press Reply
     * @return array{ok: bool, driver: string, error: string|null}
     */
    public function send(string $to, string $subject, string $text, string $html = '', ?string $replyTo = null): array
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'driver' => 'none', 'error' => 'Invalid recipient address.'];
        }

        $driver = $this->driver();

        try {
            $result = match ($driver) {
                'smtp' => $this->sendSmtp($to, $subject, $text, $html, $replyTo),
                'mail' => $this->sendMail($to, $subject, $text, $html, $replyTo),
                default => $this->sendLog($to, $subject, $text),
            };
        } catch (Throwable $e) {
            $result = ['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()];
        }

        $this->record($to, $subject, $result);

        return $result;
    }

    public function driver(): string
    {
        if (($this->config['enabled'] ?? false) !== true) {
            return 'log';
        }

        $profile = $this->profile();

        if (($profile['host'] ?? '') !== '' && ($profile['username'] ?? '') !== ''
            && class_exists(PHPMailer::class)) {
            return 'smtp';
        }

        return 'mail';
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        $profiles = $this->config['profiles'] ?? [];
        $active = (string) ($this->config['active_profile'] ?? 'cpanel');

        if (!is_array($profiles) || !isset($profiles[$active]) || !is_array($profiles[$active])) {
            return [];
        }

        /** @var array<string, mixed> */
        return $profiles[$active];
    }

    private function fromAddress(): string
    {
        $from = trim((string) ($this->config['from_email'] ?? ''));

        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false) {
            return $from;
        }

        return 'no-reply@' . $this->hostname();
    }

    /**
     * A reply address that is safe to put in a header.
     *
     * Falls back to the From address when the customer did not leave an email.
     */
    private function replyAddress(?string $candidate): string
    {
        $candidate = trim((string) $candidate);

        return $candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false
            ? $candidate
            : $this->fromAddress();
    }

    private function hostname(): string
    {
        $url = (string) (\Wiz\Core\Env::get('APP_URL', '') ?? '');
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /**
     * @return array{ok: bool, driver: string, error: string|null}
     */
    private function sendLog(string $to, string $subject, string $text): array
    {
        $line = sprintf(
            "[%s] to: %s\nsubject: %s\n\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $text,
            str_repeat('-', 72),
        );

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }

        $written = @file_put_contents($this->logDir . '/mail.log', $line, FILE_APPEND | LOCK_EX);

        return $written === false
            ? ['ok' => false, 'driver' => 'log', 'error' => 'Cannot write the mail log.']
            : ['ok' => true, 'driver' => 'log', 'error' => null];
    }

    /**
     * @return array{ok: bool, driver: string, error: string|null}
     */
    private function sendMail(string $to, string $subject, string $text, string $html, ?string $replyTo = null): array
    {
        $boundary = bin2hex(random_bytes(8));
        $from = $this->fromAddress();
        $fromName = (string) ($this->config['from_name'] ?? 'WIZ Beauty Salon');
        $reply = $this->replyAddress($replyTo);

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $this->encodeHeader($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $reply,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $text . "\r\n\r\n";

        if ($html !== '') {
            $body .= '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        $ok = @mail($to, $this->encodeHeader($subject), $body, implode("\r\n", $headers), '-f' . $from);

        return [
            'ok' => $ok,
            'driver' => 'mail',
            'error' => $ok ? null : 'The local mail transfer agent refused the message.',
        ];
    }

    /**
     * @return array{ok: bool, driver: string, error: string|null}
     */
    private function sendSmtp(string $to, string $subject, string $text, string $html, ?string $replyTo = null): array
    {
        $profile = $this->profile();
        $mailer = new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->Host = (string) ($profile['host'] ?? '');
        $mailer->Port = (int) ($profile['port'] ?? 587);
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) ($profile['username'] ?? '');
        $mailer->Password = (string) ($profile['password'] ?? '');
        $mailer->Timeout = 10;

        $encryption = (string) ($profile['encryption'] ?? 'tls');

        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($this->fromAddress(), (string) ($this->config['from_name'] ?? 'WIZ'));
        $mailer->addAddress($to);
        $mailer->addReplyTo($this->replyAddress($replyTo));
        $mailer->Subject = $subject;
        $mailer->Body = $html !== '' ? $html : $text;
        $mailer->AltBody = $text;

        if ($html !== '') {
            $mailer->isHTML(true);
        }

        $ok = $mailer->send();

        return [
            'ok' => $ok,
            'driver' => 'smtp',
            'error' => $ok ? null : $mailer->ErrorInfo,
        ];
    }

    /**
     * @param array{ok: bool, driver: string, error: string|null} $result
     */
    private function record(string $to, string $subject, array $result): void
    {
        $line = sprintf(
            "[%s] %s %s → %s%s\n",
            date('Y-m-d H:i:s'),
            $result['driver'],
            $result['ok'] ? 'sent' : 'FAILED',
            $to,
            $result['error'] === null ? '' : ' (' . $result['error'] . ')',
        );
        $line = str_replace(' → ', ' -> ', $line);

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }

        @file_put_contents($this->logDir . '/mail.log', $line . '   subject: ' . $subject . "\n", FILE_APPEND | LOCK_EX);
    }

    private function encodeHeader(string $value): string
    {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
}
