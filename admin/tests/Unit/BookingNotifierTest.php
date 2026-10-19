<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Logger;
use Wiz\Core\Mailer;
use Wiz\Core\View;
use Wiz\Services\BookingNotifier;
use Wiz\Support\SignedLink;

final class BookingNotifierTest extends TestCase
{
    private string $dir;

    private BookingNotifier $notifier;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wiz-mail-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);

        $this->notifier = new BookingNotifier(
            new Mailer([
                'enabled' => false, // the log driver: nothing leaves the machine
                'from_email' => 'no-reply@wiz.test',
                'from_name' => 'WIZ Beauty Salon',
                'profiles' => [],
            ], $this->dir),
            new View(dirname(__DIR__, 2) . '/src/Views'),
            new SignedLink(str_repeat('k', 64)),
            new Logger($this->dir),
            'https://wiz.test',
            'salon@wiz.test',
        );
    }

    protected function tearDown(): void
    {
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

    private function log(): string
    {
        $file = $this->dir . '/mail.log';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    public function testWritesToTheCustomerAndTheSalon(): void
    {
        $this->notifier->requested($this->booking());

        $log = $this->log();

        self::assertStringContainsString('to: hanna@example.com', $log);
        self::assertStringContainsString('to: salon@wiz.test', $log);
        self::assertStringContainsString('WZ261019-AC4K', $log);
    }

    public function testTheCustomerEmailCarriesTheirOwnLink(): void
    {
        $this->notifier->requested($this->booking());

        $log = $this->log();

        self::assertMatchesRegularExpression(
            '#https://wiz\.test/booking/manage/[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+#',
            $log,
        );
    }

    public function testSkipsTheCustomerWhenThereIsNoAddress(): void
    {
        $this->notifier->requested($this->booking(['customer_email' => '']));

        $log = $this->log();

        self::assertStringContainsString('to: salon@wiz.test', $log);
        self::assertStringNotContainsString('hanna@example.com', $log);
    }

    public function testTellsTheCustomerThatATimeWasConfirmed(): void
    {
        $this->notifier->statusChanged($this->booking(['status' => 'confirmed']), 'confirmed');

        self::assertStringContainsString('Your appointment is confirmed', $this->log());
    }

    public function testCancellationAlsoNotifiesTheSalon(): void
    {
        $this->notifier->statusChanged($this->booking(['status' => 'cancelled']), 'cancelled');

        $log = $this->log();

        self::assertStringContainsString('Your appointment is cancelled', $log);
        self::assertStringContainsString('to: salon@wiz.test', $log);
    }

    public function testMentionsTheOldTimeWhenAnAppointmentMoves(): void
    {
        $this->notifier->rescheduled($this->booking(['start_time' => '14:00:00', 'end_time' => '15:00:00']), 'Monday 20 October, 10:00–11:00');

        self::assertStringContainsString('Monday 20 October, 10:00', $this->log());
    }
}
