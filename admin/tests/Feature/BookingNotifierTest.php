<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Logger;
use Wiz\Core\Migrations;
use Wiz\Core\View;
use Wiz\Services\BookingNotifier;
use Wiz\Services\EmailTemplateService;
use Wiz\Support\SignedLink;
use Wiz\Tests\Support\RecordingTransport;

/**
 * What the booking emails contain, and to whom — captured at the transport, so
 * no message is ever sent.
 */
final class BookingNotifierTest extends TestCase
{
    private string $dir;

    private RecordingTransport $transport;

    private BookingNotifier $notifier;

    private Database $db;

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');

        if ($name === false || $name === '') {
            self::markTestSkipped('No database configured (DB_NAME is empty).');
        }

        $this->dir = sys_get_temp_dir() . '/wiz-mail-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);

        $this->db = new Database([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('DB_USER') ?: '',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ]);

        (new Migrations($this->db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
        $this->db->execute('DELETE FROM email_template_active');
        $this->db->execute('DELETE FROM email_template_revisions');

        $this->transport = new RecordingTransport();

        $this->notifier = new BookingNotifier(
            $this->transport,
            new EmailTemplateService($this->db, 'https://wiz.test'),
            new View(dirname(__DIR__, 2) . '/src/Views'),
            new SignedLink(str_repeat('k', 64)),
            new Logger($this->dir),
            'https://wiz.test',
            'salon@wiz.test',
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->execute('DELETE FROM email_template_active');
            $this->db->execute('DELETE FROM email_template_revisions');
        }

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function booking(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'reference' => 'WZ261019-AC4K',
            'customer_name' => 'Hanna Tesfaye',
            'customer_phone' => '0911223344',
            'customer_email' => 'hanna@example.com',
            'service_name' => 'Wave',
            'booking_date' => '2026-10-22',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'requested',
        ], $overrides);
    }

    public function testWritesToTheCustomerAndTheSalon(): void
    {
        $this->notifier->requested($this->booking());

        self::assertCount(2, $this->transport->sent);

        $customer = $this->transport->to('hanna@example.com');
        $salon = $this->transport->to('salon@wiz.test');

        self::assertCount(1, $customer);
        self::assertCount(1, $salon);
        self::assertStringContainsString('WZ261019-AC4K', $customer[0]['subject']);
        self::assertStringContainsString('WZ261019-AC4K', $customer[0]['html']);
        self::assertStringContainsString('0911223344', $salon[0]['text']);
        self::assertSame('hanna@example.com', $salon[0]['replyTo']);
    }

    public function testTheEmailShowsTheSalonLogo(): void
    {
        $this->notifier->requested($this->booking());

        $customer = $this->transport->to('hanna@example.com');

        // Absolute, because a mail client has no page to resolve it against.
        self::assertStringContainsString('src="https://wiz.test/logo.png"', $customer[0]['html']);
    }

    public function testTheCustomerEmailCarriesTheirOwnLink(): void
    {
        $this->notifier->requested($this->booking());

        $customer = $this->transport->to('hanna@example.com');

        self::assertMatchesRegularExpression(
            '#https://wiz\.test/booking/manage/[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+#',
            $customer[0]['text'],
        );
    }

    public function testSkipsTheCustomerWhenThereIsNoAddress(): void
    {
        $this->notifier->requested($this->booking(['customer_email' => '']));

        self::assertCount(1, $this->transport->sent);
        self::assertSame('salon@wiz.test', $this->transport->sent[0]['to']);
    }

    public function testTellsTheCustomerThatATimeWasConfirmed(): void
    {
        $this->notifier->statusChanged($this->booking(['status' => 'confirmed']), 'confirmed');

        $customer = $this->transport->to('hanna@example.com');

        self::assertSame('Your appointment is confirmed · WZ261019-AC4K', $customer[0]['subject']);
    }

    public function testCancellationAlsoNotifiesTheSalon(): void
    {
        $this->notifier->statusChanged($this->booking(['status' => 'cancelled']), 'cancelled');

        self::assertStringContainsString('cancelled', $this->transport->to('hanna@example.com')[0]['subject']);
        self::assertCount(1, $this->transport->to('salon@wiz.test'));
    }

    public function testMentionsTheOldTimeWhenAnAppointmentMoves(): void
    {
        $this->notifier->rescheduled(
            $this->booking(['start_time' => '14:00:00', 'end_time' => '15:00:00']),
            'Monday 20 October, 10:00–11:00',
        );

        self::assertStringContainsString('Monday 20 October, 10:00', $this->transport->to('hanna@example.com')[0]['text']);
    }

    public function testAPublishedDesignReplacesTheBuiltInLayout(): void
    {
        $id = (new EmailTemplateService($this->db, 'https://wiz.test'))->saveDraft(
            'booking_confirmed',
            'WIZ · your appointment',
            '<div><p>DESIGNED</p><p>{{message}}</p></div>',
            'DESIGNED {{message}}',
            '{"pages":[]}',
            1,
        );
        (new EmailTemplateService($this->db, 'https://wiz.test'))->publish('booking_confirmed', $id);

        $this->notifier->statusChanged($this->booking(['status' => 'confirmed']), 'confirmed');

        $customer = $this->transport->to('hanna@example.com')[0];

        self::assertSame('WIZ · your appointment', $customer['subject']);
        self::assertStringContainsString('DESIGNED', $customer['html']);
        self::assertStringContainsString('WZ261019-AC4K', $customer['text']);
    }
}
