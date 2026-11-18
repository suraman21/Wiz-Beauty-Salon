<?php
declare(strict_types=1);

namespace Wiz\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Transactional mail adapter, cloned from Bulbula.et's `Core\Mailer`.
 *
 * PHPMailer does the protocol work; this class only decides how to reach a
 * server, applies the house rules (UTF-8, a 12-second timeout, a plain-text
 * alternative for every HTML message) and turns every outcome into an array.
 * It never throws: a booking must not fail because a mail server is having a
 * bad morning.
 *
 *   smtp — authenticated SMTP, starttls or implicit TLS
 *   mail — PHP's mail(), which on cPanel hands the message to the local MTA
 */
final class Mailer
{
    /**
     * @param array<string, mixed> $config the resolved mail configuration
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * A plain-text-only message: no HTML part is created at all.
     *
     * @return array{ok: bool, error?: string, transport?: string}
     */
    public function sendText(string $to, string $subject, string $text): array
    {
        return $this->send($to, $subject, '', $text, true);
    }

    /**
     * @param string|null $replyTo where a reply should go — the customer's own
     *                             address on salon notifications
     * @return array{ok: bool, error?: string, transport?: string}
     */
    public function send(string $to, string $subject, string $html, string $text = '', bool $plainOnly = false, ?string $replyTo = null): array
    {
        if (!class_exists(PHPMailer::class)) {
            return $this->failed('PHPMailer is not installed. Run composer install.');
        }

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return $this->failed('Invalid recipient email address.');
        }

        $driver = (string) ($this->config['driver'] ?? 'smtp');

        $mail = new PHPMailer(true);

        try {
            if ($driver === 'mail') {
                $mail->isMail();
            } else {
                $host = trim((string) ($this->config['host'] ?? ''));
                $username = trim((string) ($this->config['username'] ?? ''));
                $password = (string) ($this->config['password'] ?? '');
                $encryption = (string) ($this->config['encryption'] ?? 'tls');

                if ($host === '' || $username === '' || $password === '') {
                    return $this->failed('SMTP host, username, or password is not configured.');
                }

                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = (int) ($this->config['port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                $mail->Timeout = 12;
                $mail->SMTPKeepAlive = false;

                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
            }

            $fromEmail = (string) ($this->config['from_email'] ?? '');
            $fromName = (string) ($this->config['from_name'] ?? 'WIZ Beauty Salon');

            if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
                return $this->failed('Configured sender email is invalid.');
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);

            if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(!$plainOnly);
            $mail->Subject = $subject;
            $mail->Body = $plainOnly ? $text : $html;
            $mail->AltBody = $text !== ''
                ? $text
                : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

            $mail->send();

            $this->note(true, $driver);

            return ['ok' => true, 'transport' => $driver === 'mail' ? 'mail' : 'smtp'];
        } catch (PHPMailerException) {
            $this->note(false, $driver);

            return $this->failed('Mail transport failed: ' . $mail->ErrorInfo);
        } catch (\Throwable) {
            $this->note(false, $driver);

            return $this->failed('Mail transport failed safely.');
        }
    }

    /**
     * @return array{ok: false, error: string}
     */
    private function failed(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }

    /**
     * One line per attempt — transport and outcome only. Recipients, subjects
     * and message bodies are personal data and stay out of the log.
     */
    private function note(bool $ok, string $driver): void
    {
        $this->logger?->info('Mail ' . ($ok ? 'accepted' : 'failed') . ' via ' . $driver);
    }
}
