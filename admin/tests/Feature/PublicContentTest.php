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
use Wiz\Repositories\SiteImageRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Support\ChangePolicy;
use Wiz\Support\MediaUsage;
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

    public function testCatalogueCarriesTheSalonsServicesInStaffOrder(): void
    {
        $second = $this->categories->create('Nails', 'nail', 'Hands and feet', true, 'ጥፍር');
        $first = $this->categories->create('Braids', 'braids', null, true, 'ጋሻ');
        $this->categories->create('Retired', 'retired', null, false);

        // Staff decide the order; "Nails" was added first but moved above.
        $this->db->execute('UPDATE service_categories SET sort_order = 0 WHERE id = :id', [':id' => $second]);
        $this->db->execute('UPDATE service_categories SET sort_order = 10 WHERE id = :id', [':id' => $first]);

        $body = json_decode(
            $this->bookingApi()->services(new Request('GET', '/api/v1/services'))->body(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            ['id' => $second, 'name' => 'Nails', 'slug' => 'nail', 'amharic' => 'ጥፍር', 'description' => 'Hands and feet'],
            ['id' => $first, 'name' => 'Braids', 'slug' => 'braids', 'amharic' => 'ጋሻ', 'description' => ''],
        ], array_map(
            static fn (array $service): array => [
                'id' => $service['id'],
                'name' => $service['name'],
                'slug' => $service['slug'],
                'amharic' => $service['amharic'],
                'description' => $service['description'],
            ],
            $body['services'],
        ), 'Hidden services stay hidden, and staff order wins.');

        // The salon settles prices and running times with the customer, so the
        // catalogue carries neither.
        self::assertArrayNotHasKey('price_cents', $body['services'][0]);
        self::assertArrayNotHasKey('duration_minutes', $body['services'][0]);
    }

    public function testCategoriesCarryTheirHomePageFlagToTheWebsite(): void
    {
        $leads = $this->categories->create('Hair', 'hair', null, true, null, true);
        $this->categories->create('Skin', 'skin', null, true);

        $body = json_decode(
            $this->bookingApi()->services(new Request('GET', '/api/v1/services'))->body(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $flags = [];

        foreach ($body['services'] as $service) {
            $flags[(string) $service['slug']] = $service['show_on_home'];
        }

        self::assertTrue($flags['hair'], 'A service ticked for the home page says so.');
        self::assertFalse($flags['skin'], 'A service left unticked is not on the home page.');

        // Unticking it in the panel is what takes it off the home page again.
        $this->categories->update($leads, 'Hair', null, true, null, false);

        self::assertFalse((bool) $this->categories->find($leads)['show_on_home']);
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
            new SiteImageRepository($this->db),
            $this->salon,
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new ChangePolicy(new SettingsRepository($this->db)),
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
                new SiteImageRepository($this->db),
                $this->salon,
                new BusinessHoursRepository($this->db),
                new ClosureRepository($this->db),
                new ChangePolicy(new SettingsRepository($this->db)),
            ))->salon(new Request('GET', '/api/v1/salon'))->body(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('', $body['contact']['phone']);
        self::assertSame('', $body['contact']['email']);
        self::assertTrue($this->salon->isEmpty());
    }

    public function testImagesEndpointPublishesEverySlotTheLayoutUses(): void
    {
        $this->db->execute("DELETE FROM site_images");
        $this->db->execute(
            "INSERT INTO site_images (slot, label, image_path, alt, sort_order)
             VALUES ('hero', 'Home page hero', 'aaaaaaaaaaaaaaaaaaaaaaaa.jpg', 'Hero portrait', 10),
                    ('before', 'Before the change', '', 'Before', 20)",
        );

        $body = json_decode($this->contentApi()->images(new Request('GET', '/api/v1/images'))->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('hero', $body['images']);
        self::assertSame('/uploads/aaaaaaaaaaaaaaaaaaaaaaaa.jpg', $body['images']['hero']['url']);
        self::assertSame('Hero portrait', $body['images']['hero']['alt']);
        self::assertArrayNotHasKey('before', $body['images'], 'An empty slot falls back to the bundled picture.');
    }

    public function testGalleryPublishesCategoriesSoTheFilterWorksOnLivePhotos(): void
    {
        $categoryId = $this->categories->create('Nail', 'nail', null, true, 'ጥፍር');

        $this->db->execute('DELETE FROM gallery_items');
        $this->db->execute(
            'INSERT INTO gallery_items (image_path, category_id, title, caption, is_published, sort_order)
             VALUES (:path, :category, :title, NULL, 1, 10)',
            [':path' => 'bbbbbbbbbbbbbbbbbbbbbbbb.jpg', ':category' => $categoryId, ':title' => 'Quiet polish'],
        );

        $body = json_decode($this->contentApi()->gallery(new Request('GET', '/api/v1/gallery'))->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Nail', $body['photos'][0]['category']);
        self::assertSame('nail', $body['photos'][0]['category_slug']);
        self::assertSame('Nail', $body['categories'][0]['name']);
        self::assertSame(1, $body['categories'][0]['photo_count']);
    }

    public function testThePhotoStaffUploadForAServiceReachesTheWebsite(): void
    {
        $categoryId = $this->categories->create('Braids', 'braids', null, true, null);
        $this->categories->setImage($categoryId, 'cccccccccccccccccccccccc.jpg');

        $body = json_decode($this->bookingApi()->services(new Request('GET', '/api/v1/services'))->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('/uploads/cccccccccccccccccccccccc.jpg', $body['services'][0]['image_url']);
    }

    public function testAFileSharedByTwoScreensIsNotDeletedWithOne(): void
    {
        $categoryId = $this->categories->create('Nail', 'nail', null, true, null);
        $this->categories->setImage($categoryId, 'eeeeeeeeeeeeeeeeeeeeeeee.jpg');

        $this->db->execute('DELETE FROM gallery_items');
        $this->db->execute(
            'INSERT INTO gallery_items (image_path, category_id, title, caption, is_published, sort_order)
             VALUES (:path, :category, :title, NULL, 1, 10)',
            [':path' => 'eeeeeeeeeeeeeeeeeeeeeeee.jpg', ':category' => $categoryId, ':title' => 'Shared'],
        );

        $usage = new MediaUsage($this->db);
        $galleryId = (int) $this->db->scalar('SELECT id FROM gallery_items LIMIT 1');

        self::assertTrue(
            $usage->usedElsewhere('eeeeeeeeeeeeeeeeeeeeeeee.jpg', 'gallery_items', $galleryId),
            'The category still needs the file, so deleting the photo must keep it.',
        );

        $this->db->execute('DELETE FROM gallery_items WHERE id = :id', [':id' => $galleryId]);

        self::assertFalse(
            $usage->usedElsewhere('eeeeeeeeeeeeeeeeeeeeeeee.jpg', 'service_categories', $categoryId),
            'Once nothing else points at it, the file is free to delete.',
        );
    }

    private function contentApi(): ContentApiController
    {
        return new ContentApiController(
            new GalleryRepository($this->db),
            new ReviewRepository($this->db),
            new SiteImageRepository($this->db),
            $this->salon,
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new ChangePolicy(new SettingsRepository($this->db)),
        );
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
            new BookingRepository($this->db),
            '',
            '/uploads',
            null,
        );
    }
}
