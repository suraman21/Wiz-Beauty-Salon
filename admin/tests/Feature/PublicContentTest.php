<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Controllers\Api\BookingApiController;
use Wiz\Controllers\Api\ContentApiController;
use Wiz\Core\Database;
use Wiz\Core\Logger;
use Wiz\Core\Migrations;
use Wiz\Core\Request;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\SalonDetails;
use Wiz\Support\SignedLink;

/**
 * The two endpoints that carry the panel's content to the website.
 *
 * The promise being tested is the whole point of the sync: what staff type in
 * the panel is what a visitor reads — a category's English and Amharic names in
 * the order staff arranged them, its services with their prices, the salon's
 * phone number, and the opening hours. Nothing here is duplicated in the site's
 * source, so nothing here can drift.
 */
final class PublicContentTest extends TestCase
{
    private Database $db;

    private CategoryRepository $categories;

    private ServiceRepository $services;

    private SalonDetails $salon;

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

        $this->db->execute('DELETE FROM services');
        $this->db->execute('DELETE FROM service_categories');
        $this->db->execute('DELETE FROM gallery_items');
        $this->db->execute('DELETE FROM reviews');
        $this->db->execute("DELETE FROM settings WHERE setting_key LIKE 'salon.%'");

        $this->categories = new CategoryRepository($this->db);
        $this->services = new ServiceRepository($this->db);
        $this->salon = new SalonDetails(new SettingsRepository($this->db));
    }

    public function testCatalogueCarriesCategoriesAndPricesForTheWebsite(): void
    {
        $second = $this->categories->create('Braids', 'braids', null, true, 'ጋሻ');
        $first = $this->categories->create('Nail', 'nail', null, true, 'ጥፍር');
        $this->categories->create('Hidden', 'hidden', null, false);

        // Staff decide the order; "Braids" was added first but moved above.
        $this->db->execute('UPDATE service_categories SET sort_order = 0 WHERE id = :id', [':id' => $second]);
        $this->db->execute('UPDATE service_categories SET sort_order = 10 WHERE id = :id', [':id' => $first]);

        $this->services->create([
            'category_id' => $first,
            'name' => 'Shellac',
            'slug' => 'shellac',
            'short_description' => 'Long-lasting colour',
            'description' => null,
            'price_cents' => 95000,
            'duration_minutes' => 45,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 1,
        ]);

        $controller = $this->bookingApi();
        $response = $controller->services(new Request('GET', '/api/v1/services'));
        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            ['id' => $second, 'name' => 'Braids', 'slug' => 'braids', 'amharic' => 'ጋሻ'],
            ['id' => $first, 'name' => 'Nail', 'slug' => 'nail', 'amharic' => 'ጥፍር'],
        ], array_map(
            static fn (array $category): array => [
                'id' => $category['id'],
                'name' => $category['name'],
                'slug' => $category['slug'],
                'amharic' => $category['amharic'],
            ],
            $body['categories'],
        ), 'Hidden categories stay hidden, and staff order wins.');

        self::assertCount(1, $body['services']);
        self::assertSame('Shellac', $body['services'][0]['name']);
        self::assertSame('shellac', $body['services'][0]['slug']);
        self::assertSame(95000, $body['services'][0]['price_cents']);
        self::assertSame('Nail', $body['services'][0]['category']);
        self::assertSame('Long-lasting colour', $body['services'][0]['description']);
        self::assertTrue($body['services'][0]['featured']);
    }

    public function testHidingACategoryHidesItsServicesButNotUncategorisedOnes(): void
    {
        $hidden = $this->categories->create('Retired', 'retired', null, false);
        $shown = $this->categories->create('Nail', 'nail', null, true);

        foreach ([[$hidden, 'Gone'], [$shown, 'Kept'], [null, 'Loose']] as $index => [$categoryId, $name]) {
            $this->services->create([
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => 'svc-' . $index,
                'short_description' => null,
                'description' => null,
                'price_cents' => null,
                'duration_minutes' => 60,
                'image_path' => null,
                'is_active' => 1,
                'is_featured' => 0,
            ]);
        }

        $body = json_decode(
            $this->bookingApi()->services(new Request('GET', '/api/v1/services'))->body(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $names = array_column($body['services'], 'name');

        self::assertContains('Kept', $names);
        self::assertContains('Loose', $names, 'A service with no category is still sellable.');
        self::assertNotContains('Gone', $names, 'Hiding a category hides what is filed under it.');
    }

    public function testRenamingACategoryIsVisibleToTheWebsiteAtOnce(): void
    {
        $id = $this->categories->create('Hair Cut', 'hair-cut', null, true, 'የፀጉር ቁርጥ');

        $this->categories->update($id, 'Cuts', null, true, 'ቁርጥ');

        $row = $this->categories->published()[0];

        self::assertSame('Cuts', $row['name']);
        self::assertSame('ቁርጥ', $row['amharic']);
    }

    public function testSalonEndpointPublishesDetailsAndHours(): void
    {
        $this->salon->save([
            SalonDetails::PHONE => '+251 91 234 5678',
            SalonDetails::WHATSAPP => '0911223344',
            SalonDetails::EMAIL => 'hello@wiz.et',
            SalonDetails::INSTAGRAM => 'https://instagram.com/wiz.beauty/',
            SalonDetails::TIKTOK => '@wizbeauty',
            SalonDetails::ADDRESS => 'Bole, Addis Ababa',
        ]);

        $controller = new ContentApiController(
            new GalleryRepository($this->db),
            new ReviewRepository($this->db),
            $this->salon,
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
        );

        $response = $controller->salon(new Request('GET', '/api/v1/salon'));
        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('+251 91 234 5678', $body['contact']['phone']);
        self::assertSame('wiz.beauty', $body['contact']['instagram'], 'A profile link is stored as a handle.');
        self::assertSame('wizbeauty', $body['contact']['tiktok']);
        self::assertSame('Bole, Addis Ababa', $body['contact']['address']);

        self::assertCount(7, $body['hours'], 'A full week, Sunday first, as the panel stores it.');

        foreach ($body['hours'] as $hour) {
            self::assertArrayHasKey('is_closed', $hour);
            self::assertMatchesRegularExpression(
                '/^\d{2}:\d{2}$/',
                (string) ($hour['is_closed'] ? '00:00' : $hour['opens_at']),
            );
        }

        self::assertStringContainsString('max-age', $response->headers()['Cache-Control'] ?? '');
    }

    public function testBlankDetailsArePublishedAsBlankNeverInvented(): void
    {
        $this->salon->save([SalonDetails::PHONE => '  ', SalonDetails::EMAIL => '']);

        $body = json_decode(
            (new ContentApiController(
                new GalleryRepository($this->db),
                new ReviewRepository($this->db),
                $this->salon,
                new BusinessHoursRepository($this->db),
                new ClosureRepository($this->db),
            ))->salon(new Request('GET', '/api/v1/salon'))->body(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('', $body['contact']['phone']);
        self::assertSame('', $body['contact']['email']);
        self::assertTrue($this->salon->isEmpty());
    }

    private function bookingApi(): BookingApiController
    {
        $calculator = new SlotCalculator();
        $availability = new AvailabilityService(
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new BookingSlotRepository($this->db),
            new SettingsRepository($this->db),
            $calculator,
        );

        return new BookingApiController(
            $availability,
            new BookingService(
                $this->db,
                new BookingRepository($this->db),
                new BookingSlotRepository($this->db),
                $this->services,
                $availability,
                $calculator,
                new ActivityLogRepository($this->db),
                new Logger(sys_get_temp_dir() . '/wiz-test-logs'),
            ),
            $this->services,
            $this->categories,
            new RateLimitRepository($this->db),
            '',
            null,
        );
    }
}
