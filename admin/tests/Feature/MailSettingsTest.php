<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\SettingsRepository;
use Wiz\Support\MailSettings;

/**
 * Panel values on top of the file defaults, and the sealed password.
 */
final class MailSettingsTest extends TestCase
{
    private Database $db;
    private SettingsRepository $settings;

    /** @var list<array<string, mixed>> */
    private array $saved = [];

    /** @var array<string, mixed> */
    private array $config = [
        'enabled' => false,
        'active_profile' => 'cpanel',
        'from_name' => 'From the file',
        'from_email' => 'file@example.test',
        'salon_email' => 'salon-file@example.test',
        'profiles' => [
            'cpanel' => [
                'label' => 'cPanel SMTP',
                'host' => 'mail.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'mailer@example.test',
                'password' => 'password-from-file',
            ],
        ],
    ];

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');

        if ($name === false || $name === '') {
            self::markTestSkipped('No database configured (DB_NAME is empty).');
        }

        $this->db = new Database([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('DB_USER') ?: '',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ]);

        (new Migrations($this->db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        $this->saved = $this->db->all("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'mail.%'");
        $this->db->execute("DELETE FROM settings WHERE setting_key LIKE 'mail.%'");

        $this->settings = new SettingsRepository($this->db);
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM settings WHERE setting_key LIKE 'mail.%'");

        foreach ($this->saved as $row) {
            $this->db->execute(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)',
                [':key' => (string) $row['setting_key'], ':value' => $row['setting_value']],
            );
        }
    }

    public function testWithNothingSavedTheFileValuesAreUsed(): void
    {
        $mail = $this->mail();

        self::assertFalse($mail->enabled());
        self::assertSame('From the file', $mail->fromName());
        self::assertSame('file@example.test', $mail->fromEmail());
        self::assertSame('salon-file@example.test', $mail->salonEmail());
        self::assertFalse($mail->passwordSaved());

        $config = $mail->config();
        self::assertSame('password-from-file', $config['profiles']['cpanel']['password'] ?? null);
    }

    public function testSavedValuesWinOverTheFile(): void
    {
        $this->mail()->save(true, 'WIZ Salon', 'hello@wiz.test', 'inbox@wiz.test');

        $mail = $this->mail();

        self::assertTrue($mail->enabled());
        self::assertSame('WIZ Salon', $mail->fromName());
        self::assertSame('hello@wiz.test', $mail->fromEmail());
        self::assertSame('inbox@wiz.test', $mail->salonEmail());

        $config = $mail->config();
        self::assertTrue($config['enabled']);
        self::assertSame('hello@wiz.test', $config['from_email']);
        self::assertSame('inbox@wiz.test', $config['salon_email']);
    }

    public function testSwitchingDeliveryOffWithoutChangingAnythingElse(): void
    {
        $this->mail()->save(false, 'From the file', 'file@example.test', 'salon-file@example.test');

        self::assertFalse($this->mail()->enabled());
    }

    public function testThePasswordIsSealedAndReadsBack(): void
    {
        $mail = $this->mail();
        $mail->savePassword('s3cret-mail-password');

        self::assertTrue($mail->passwordSaved());

        $stored = $this->db->scalar(
            'SELECT setting_value FROM settings WHERE setting_key = :key',
            [':key' => 'mail.smtp_password'],
        );

        self::assertIsString($stored);
        self::assertStringNotContainsString('s3cret', $stored);

        $config = $this->mail()->config();
        self::assertSame('s3cret-mail-password', $config['profiles']['cpanel']['password'] ?? null);
    }

    public function testAnEmptyPasswordKeepsTheSavedOne(): void
    {
        $mail = $this->mail();
        $mail->savePassword('first-password');
        $mail->savePassword('');

        $config = $this->mail()->config();

        self::assertSame('first-password', $config['profiles']['cpanel']['password'] ?? null);
    }

    public function testWithoutAnAppKeyThePasswordIsNotSaved(): void
    {
        $mail = new MailSettings($this->settings, $this->config, '');

        $this->expectException(\RuntimeException::class);
        $mail->savePassword('s3cret');
    }

    private function mail(): MailSettings
    {
        return new MailSettings($this->settings, $this->config, 'test-app-key');
    }
}
