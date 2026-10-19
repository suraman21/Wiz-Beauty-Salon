<?php
declare(strict_types=1);

namespace Wiz\Support;

use RuntimeException;
use Wiz\Repositories\SettingsRepository;

/**
 * The mail configuration the panel actually uses.
 *
 * config/mail.php holds the values the hosting account was set up with; the
 * settings screen can override the parts a salon can reasonably look after —
 * whether mail goes out at all, the name and address it comes from, where
 * notifications land, and the mailbox password.
 *
 * The password is the only secret here, so it is never stored as plain text:
 * it is sealed with AES-256-GCM using a key derived from APP_KEY and only
 * decrypted in memory when a message is being sent.
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
     * The config array Mailer expects, panel values winning over the file.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $config = $this->config;

        $config['enabled'] = $this->enabled();
        $config['from_name'] = $this->fromName();
        $config['from_email'] = $this->fromEmail();
        $config['salon_email'] = $this->salonEmail();

        $active = $this->activeProfile();
        $profiles = is_array($config['profiles'] ?? null) ? $config['profiles'] : [];
        $profile = is_array($profiles[$active] ?? null) ? $profiles[$active] : [];

        $password = $this->password();

        if ($password !== null) {
            $profile['password'] = $password;
        }

        $profiles[$active] = $profile;
        $config['profiles'] = $profiles;

        return $config;
    }

    public function enabled(): bool
    {
        $stored = $this->settings->get('mail.enabled', '');

        if ($stored === '') {
            return ($this->config['enabled'] ?? false) === true;
        }

        return $stored === '1';
    }

    public function fromName(): string
    {
        return $this->text('mail.from_name', (string) ($this->config['from_name'] ?? 'WIZ Beauty Salon'));
    }

    public function fromEmail(): string
    {
        return $this->text('mail.from_email', (string) ($this->config['from_email'] ?? ''));
    }

    public function salonEmail(): string
    {
        $fallback = (string) ($this->config['salon_email'] ?? '');

        if (trim($fallback) === '') {
            $fallback = $this->fromEmail();
        }

        return $this->text('mail.salon_email', $fallback);
    }

    /**
     * The SMTP account the hosting account was given. Read-only in the panel:
     * changing it is a hosting decision, not a daily one.
     */
    public function smtpUsername(): string
    {
        $profile = $this->profileConfig();

        return (string) ($profile['username'] ?? '');
    }

    public function smtpHost(): string
    {
        $profile = $this->profileConfig();
        $host = (string) ($profile['host'] ?? '');
        $port = (int) ($profile['port'] ?? 0);

        return $host === '' ? '' : $host . ($port > 0 ? ':' . $port : '');
    }

    public function passwordSaved(): bool
    {
        return $this->password() !== null;
    }

    /**
     * Store the three visible fields, and the password only when one was typed
     * — an empty box means "keep the one already saved".
     */
    public function save(bool $enabled, string $fromName, string $fromEmail, string $salonEmail): void
    {
        $this->settings->put('mail.enabled', $enabled ? '1' : '0');
        $this->settings->put('mail.from_name', trim($fromName));
        $this->settings->put('mail.from_email', trim($fromEmail));
        $this->settings->put('mail.salon_email', trim($salonEmail));
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

        return trim($stored) === '' ? $fallback : trim($stored);
    }

    private function password(): ?string
    {
        $stored = $this->settings->get(self::PASSWORD_KEY, '');

        if ($stored === '') {
            return null;
        }

        return $this->open($stored);
    }

    private function activeProfile(): string
    {
        $active = (string) ($this->config['active_profile'] ?? 'cpanel');

        return $active === '' ? 'cpanel' : $active;
    }

    /** @return array<string, mixed> */
    private function profileConfig(): array
    {
        $profiles = $this->config['profiles'] ?? null;
        $active = $this->activeProfile();

        if (!is_array($profiles) || !is_array($profiles[$active] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $profiles[$active];
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
