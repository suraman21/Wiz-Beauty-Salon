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
        'driver' => 'smtp',
        'host' => 'mail.example.test',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'mailer@example.test',
        'password' => 'password-from-file',
        'from_name' => 'From the file',
        'from_email' => 'file@example.test',
        'salon_email' => 'salon-file@example.test',
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
        if (!isset($this->db)) {
            return;
        }

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

        self::assertSame('smtp', $mail->driver());
        self::assertSame('mail.example.test', $mail->host());
        self::assertSame(587, $mail->port());
        self::assertSame('tls', $mail->encryption());
        self::assertSame('mailer@example.test', $mail->username());
        self::assertSame('From the file', $mail->fromName());
        self::assertSame('file@example.test', $mail->fromEmail());
        self::assertSame('salon-file@example.test', $mail->salonEmail());
        self::assertTrue($mail->passwordSaved());

        $config = $mail->config();
        self::assertSame('password-from-file', $config['password']);
        self::assertSame('smtp', $config['driver']);
    }

    public function testSavedValuesWinOverTheFile(): void
    {
        $mail = $this->mail();
        $mail->save([
            'driver' => 'mail',
            'host' => 'smtp.wiz.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'hello@wiz.test',
            'from_name' => 'WIZ Salon',
            'from_email' => 'hello@wiz.test',
            'salon_email' => 'inbox@wiz.test',
        ]);

        $fresh = $this->mail();

        self::assertSame('mail', $fresh->driver());
        self::assertSame('smtp.wiz.test', $fresh->host());
        self::assertSame(465, $fresh->port());
        self::assertSame('ssl', $fresh->encryption());
        self::assertSame('hello@wiz.test', $fresh->username());
        self::assertSame('WIZ Salon', $fresh->fromName());
        self::assertSame('inbox@wiz.test', $fresh->salonEmail());

        $config = $fresh->config();
        self::assertSame('mail', $config['driver']);
        self::assertSame('inbox@wiz.test', $config['salon_email']);
    }

    public function testAnImpossibleStoredDriverFallsBackToSmtp(): void
    {
        $this->settings->put('mail.driver', 'carrier-pigeon');

        self::assertSame('smtp', $this->mail()->driver());
    }

    public function testTheSalonFallsBackToTheFromAddress(): void
    {
        $config = $this->config;
        $config['salon_email'] = '';

        $mail = new MailSettings($this->settings, $config, 'test-app-key');

        self::assertSame('file@example.test', $mail->salonEmail());
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

        self::assertSame('s3cret-mail-password', $this->mail()->config()['password']);
    }

    public function testAnEmptyPasswordKeepsTheSavedOne(): void
    {
        $mail = $this->mail();
        $mail->savePassword('first-password');
        $mail->savePassword('');

        self::assertSame('first-password', $this->mail()->config()['password']);
    }

    public function testWithoutAnAppKeyThePasswordIsNotSaved(): void
    {
        $mail = new MailSettings($this->settings, $this->config, '');

        $this->expectException(\RuntimeException::class);
        $mail->savePassword('s3cret');
    }

    public function testReadinessIsGreenOnlyWhenEverySmtpFieldIsPresent(): void
    {
        self::assertSame('smtp', $this->mail()->readiness()['state']);
        self::assertSame([], $this->mail()->readiness()['missing']);
    }

    public function testReadinessNamesWhatIsMissingInsteadOfClaimingSmtp(): void
    {
        $file = $this->config;
        $file['host'] = '';
        $file['username'] = '';
        $file['password'] = '';

        $mail = new MailSettings($this->settings, $file, 'test-app-key');

        self::assertSame('incomplete', $mail->readiness()['state']);
        self::assertSame(
            ['mail server', 'mailbox username', 'mailbox password'],
            $mail->readiness()['missing'],
        );
    }

    public function testReadinessFollowsTheWebHostDriverEvenWithBlankSmtpFields(): void
    {
        $this->mail()->save([
            'driver' => 'mail',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'from_name' => 'WIZ Beauty Salon',
            'from_email' => 'no-reply@wiz.test',
            'salon_email' => 'admin@wiz.test',
        ]);

        $fresh = $this->mail();

        self::assertSame('mail', $fresh->readiness()['state']);
        self::assertSame([], $fresh->readiness()['missing']);
    }

    private function mail(): MailSettings
    {
        return new MailSettings($this->settings, $this->config, 'test-app-key');
    }
}
