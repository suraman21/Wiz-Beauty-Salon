<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Support\Slug;

/**
 * Exercises the content schema against a real MariaDB instance.
 *
 * These are the rules that a mock cannot check: foreign keys, the ordering swap
 * and the unique slugs. The suite skips itself when no database is configured,
 * so a developer without one still gets a green run — CI always has one.
 */
final class ContentSchemaTest extends TestCase
{
    private Database $db;

    private CategoryRepository $categories;

    private ServiceRepository $services;

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

        // Content tables only: the seeded administrator must survive.
        $this->db->execute('DELETE FROM reviews');
        $this->db->execute('DELETE FROM gallery_items');
        $this->db->execute('DELETE FROM services');
        $this->db->execute('DELETE FROM service_categories');

        $this->categories = new CategoryRepository($this->db);
        $this->services = new ServiceRepository($this->db);
    }

    public function testStoresCategoryAndService(): void
    {
        $categoryId = $this->categories->create('Hair', 'hair', null, true);

        $serviceId = $this->services->create([
            'category_id' => $categoryId,
            'name' => 'Silk press',
            'slug' => 'silk-press',
            'short_description' => 'Smooth finish',
            'description' => null,
            'price_cents' => 120000,
            'duration_minutes' => 90,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 0,
        ]);

        $row = $this->services->find($serviceId);

        self::assertNotNull($row);
        self::assertSame('Silk press', $row['name']);
        self::assertSame(120000, (int) $row['price_cents']);
        self::assertSame(90, (int) $row['duration_minutes']);

        $listed = $this->services->allWithCategory();

        self::assertCount(1, $listed);
        self::assertSame('Hair', $listed[0]['category_name']);
    }

    public function testRejectsADuplicateSlug(): void
    {
        $this->categories->create('Hair', 'hair', null, true);

        self::assertTrue($this->categories->slugExists('hair'));
        self::assertFalse($this->categories->slugExists('nails'));

        $this->expectException(\PDOException::class);
        $this->categories->create('Hair again', 'hair', null, true);
    }

    public function testDeletingACategoryKeepsItsServices(): void
    {
        $categoryId = $this->categories->create('Nails', 'nails', null, true);
        $serviceId = $this->services->create([
            'category_id' => $categoryId,
            'name' => 'Gel manicure',
            'slug' => 'gel-manicure',
            'short_description' => null,
            'description' => null,
            'price_cents' => null,
            'duration_minutes' => null,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 0,
        ]);

        $this->categories->delete($categoryId);

        $row = $this->services->find($serviceId);

        self::assertNotNull($row);
        self::assertNull($row['category_id']);
    }

    public function testMovingAServiceSwapsItWithItsNeighbour(): void
    {
        $categoryId = $this->categories->create('Spa', 'spa', null, true);

        $first = $this->createService($categoryId, 'First');
        $second = $this->createService($categoryId, 'Second');
        $third = $this->createService($categoryId, 'Third');

        $this->services->move($third, 'up');

        $order = array_map(
            static fn (array $row): string => (string) $row['name'],
            $this->services->allWithCategory(),
        );

        self::assertSame(['First', 'Third', 'Second'], $order);

        // Only the two swapped rows changed places.
        self::assertLessThan(
            (int) $this->services->find($second)['sort_order'],
            (int) $this->services->find($first)['sort_order'],
        );
    }

    public function testMovingBeyondTheEndDoesNothing(): void
    {
        $categoryId = $this->categories->create('Skin', 'skin', null, true);
        $only = $this->createService($categoryId, 'Only one');

        self::assertFalse($this->services->move($only, 'up'));
        self::assertFalse($this->services->move($only, 'down'));
    }

    public function testOrderingRespectsCategoryBoundaries(): void
    {
        $hair = $this->categories->create('Hair', 'hair', null, true);
        $nails = $this->categories->create('Nails', 'nails', null, true);

        $this->createService($hair, 'Hair one');
        $this->createService($nails, 'Nails one');

        $rows = $this->services->allWithCategory();

        self::assertSame(['Hair one', 'Nails one'], array_map(static fn (array $r): string => (string) $r['name'], $rows));
    }

    public function testGalleryItemsStartUnpublished(): void
    {
        $gallery = new GalleryRepository($this->db);
        $id = $gallery->create('abcdef0123456789abcdef01.jpg', 'Bridal', null, false);

        $item = $gallery->find($id);

        self::assertNotNull($item);
        self::assertSame(0, (int) $item['is_published']);
        self::assertSame([], $gallery->published());

        $gallery->setPublished($id, true);

        self::assertCount(1, $gallery->published());
    }

    public function testReviewsWaitForApproval(): void
    {
        $reviews = new ReviewRepository($this->db);
        $id = $reviews->create('Hanna T.', 5, 'Lovely work.', null, false);

        self::assertSame(1, $reviews->countPending());
        self::assertSame(0, (int) $reviews->find($id)['is_approved']);

        $reviews->setApproved($id, true);

        self::assertSame(0, $reviews->countPending());
        self::assertSame(1, (int) $reviews->find($id)['is_approved']);
    }

    public function testRatingIsClampedToTheValidRange(): void
    {
        $reviews = new ReviewRepository($this->db);

        $low = $reviews->create('Low', 0, 'Text', null, false);
        $high = $reviews->create('High', 9, 'Text', null, false);

        self::assertSame(1, (int) $reviews->find($low)['rating']);
        self::assertSame(5, (int) $reviews->find($high)['rating']);
    }

    private function createService(int $categoryId, string $name): int
    {
        return $this->services->create([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => Slug::unique(Slug::make($name), fn (string $slug): bool => $this->services->slugExists($slug)),
            'short_description' => null,
            'description' => null,
            'price_cents' => null,
            'duration_minutes' => null,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 0,
        ]);
    }
}
