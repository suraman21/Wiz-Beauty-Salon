<?php
declare(strict_types=1);

namespace Wiz\Support;

use RuntimeException;
use Wiz\Repositories\SettingsRepository;

/**
 * The mail configuration the panel actually uses.
 *
 * config/mail.php holds what the hosting account was set up with; the settings
 * screen overrides the parts a salon can reasonably look after — the transport,
 * its server and account, the address it comes from, where notifications land,
 * and the mailbox password.
 *
 * The password is the only secret here, so it is never stored as plain text: it
 * is sealed with AES-256-GCM under a key derived from APP_KEY and only opened
 * in memory when a message is being sent.
 */
final class MailSettings
{
    private const PASSWORD_KEY = 'mail.smtp_password';

    /** @param array<string, mixed> $config the mail.php array */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly array $config,
        private readonly string $appKey,
    ) {
    }

    /**
     * The flat array Mailer expects, panel values winning over the file.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'driver' => $this->driver(),
            'host' => $this->host(),
            'port' => $this->port(),
            'encryption' => $this->encryption(),
            'username' => $this->username(),
            'password' => (string) ($this->password() ?? $this->fileValue('password')),
            'from_email' => $this->fromEmail(),
            'from_name' => $this->fromName(),
            'salon_email' => $this->salonEmail(),
        ];
    }

    public function driver(): string
    {
        $driver = $this->text('mail.driver', $this->fileText('driver', 'smtp'));

        return in_array($driver, ['smtp', 'mail'], true) ? $driver : 'smtp';
    }

    public function host(): string
    {
        return $this->text('mail.host', $this->fileText('host'));
    }

    public function port(): int
    {
        $stored = $this->settings->get('mail.port', '');
        $port = $stored === '' ? (int) ($this->config['port'] ?? 587) : (int) $stored;

        return $port >= 1 && $port <= 65_535 ? $port : 587;
    }

    public function encryption(): string
    {
        $value = $this->text('mail.encryption', $this->fileText('encryption', 'tls'));

        return in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
    }

    public function username(): string
    {
        return $this->text('mail.username', $this->fileText('username'));
    }

    public function fromName(): string
    {
        return $this->text('mail.from_name', $this->fileText('from_name', 'WIZ Beauty Salon'));
    }

    public function fromEmail(): string
    {
        return $this->text('mail.from_email', $this->fileText('from_email'));
    }

    public function salonEmail(): string
    {
        $fallback = $this->fileText('salon_email');

        if ($fallback === '') {
            $fallback = $this->fromEmail();
        }

        return $this->text('mail.salon_email', $fallback);
    }

    public function passwordSaved(): bool
    {
        return $this->password() !== null || $this->fileValue('password') !== '';
    }

    /**
     * Store the visible fields. The password is handled separately because an
     * empty box means "keep the one already saved".
     *
     * @param array{driver: string, host: string, port: int, encryption: string, username: string, from_name: string, from_email: string, salon_email: string} $values
     */
    public function save(array $values): void
    {
        $this->settings->put('mail.driver', $values['driver']);
        $this->settings->put('mail.host', trim($values['host']));
        $this->settings->put('mail.port', (string) $values['port']);
        $this->settings->put('mail.encryption', $values['encryption']);
        $this->settings->put('mail.username', trim($values['username']));
        $this->settings->put('mail.from_name', trim($values['from_name']));
        $this->settings->put('mail.from_email', trim($values['from_email']));
        $this->settings->put('mail.salon_email', trim($values['salon_email']));
    }

    public function savePassword(string $plain): void
    {
        if (trim($plain) === '') {
            return;
        }

        $this->settings->put(self::PASSWORD_KEY, $this->seal($plain));
    }

    private function text(string $key, string $fallback): string
    {
        $stored = $this->settings->get($key, '');

        return trim($stored) === '' ? trim($fallback) : trim($stored);
    }

    private function fileText(string $key, string $fallback = ''): string
    {
        return trim((string) ($this->config[$key] ?? $fallback));
    }

    private function fileValue(string $key): string
    {
        return (string) ($this->config[$key] ?? '');
    }

    private function password(): ?string
    {
        $stored = $this->settings->get(self::PASSWORD_KEY, '');

        if ($stored === '') {
            return null;
        }

        return $this->open($stored);
    }

    private function seal(string $plain): string
    {
        $key = $this->key();

        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set, so the password cannot be stored safely.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $sealed = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($sealed === false) {
            throw new RuntimeException('The password could not be encrypted.');
        }

        return base64_encode($iv . $tag . $sealed);
    }

    private function open(string $blob): ?string
    {
        $key = $this->key();
        $raw = base64_decode($blob, true);

        if ($key === '' || $raw === false || strlen($raw) <= 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $sealed = substr($raw, 28);

        $plain = openssl_decrypt($sealed, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? null : $plain;
    }

    private function key(): string
    {
        $appKey = trim($this->appKey);

        return $appKey === '' ? '' : hash('sha256', $appKey, true);
    }
}
