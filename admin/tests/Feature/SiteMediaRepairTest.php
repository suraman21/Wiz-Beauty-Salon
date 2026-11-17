<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Support\SiteMedia;

/**
 * The packaged pictures keep the site looking finished.
 *
 * The case that matters is a row that points at a file which is no longer
 * there: the browser shows a broken image, and the gallery, the category cards
 * and the hero all inherit it. Seeding must repair that without ever
 * overwriting a picture staff chose, and without filling a slot they emptied
 * on purpose.
 */
final class SiteMediaRepairTest extends TestCase
{
    private Database $db;

    private string $uploads;

    /** @var array<string, string|null> */
    private array $before = [];

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

        $this->uploads = sys_get_temp_dir() . '/wiz-media-' . bin2hex(random_bytes(4));
        mkdir($this->uploads, 0o775, true);

        $_ENV['UPLOAD_PATH'] = $this->uploads;
        $_SERVER['UPLOAD_PATH'] = $this->uploads;
        putenv('UPLOAD_PATH=' . $this->uploads);

        $this->remember('SELECT image_path FROM site_images WHERE slot = :slot', [':slot' => 'hero']);
        $this->remember('SELECT image_path FROM site_images WHERE slot = :slot', [':slot' => 'skin']);
        $this->remember('SELECT image_path FROM service_categories WHERE slug = :slug', [':slug' => 'nail']);
    }

    protected function tearDown(): void
    {
        // setUp() skips the test when no database is configured; nothing was
        // created in that case, so there is nothing to undo.
        if (!isset($this->db)) {
            return;
        }

        $this->restore('hero', $this->before['hero'] ?? null);
        $this->restore('skin', $this->before['skin'] ?? null);

        $nail = $this->before['nail'] ?? null;

        $this->db->execute(
            'UPDATE service_categories SET image_path = :path WHERE slug = :slug',
            [':path' => $nail, ':slug' => 'nail'],
        );

        foreach (glob($this->uploads . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->uploads);
        putenv('UPLOAD_PATH');
        unset($_ENV['UPLOAD_PATH'], $_SERVER['UPLOAD_PATH']);
    }

    public function testASlotPointingAtADeletedFileGetsThePackagedPictureBack(): void
    {
        $this->db->execute(
            'UPDATE site_images SET image_path = :path WHERE slot = :slot',
            [':path' => 'ffffffffffffffffffffffff.jpg', ':slot' => 'hero'],
        );

        $counts = (new SiteMedia())->install($this->db);

        self::assertGreaterThan(0, $counts['files'], 'The packaged files were copied into the uploads folder.');

        $path = (string) $this->db->scalar('SELECT image_path FROM site_images WHERE slot = :slot', [':slot' => 'hero']);

        self::assertNotSame('ffffffffffffffffffffffff.jpg', $path);
        self::assertFileExists($this->uploads . '/' . $path);
    }

    public function testASlotEmptiedOnPurposeStaysEmpty(): void
    {
        $this->db->execute('UPDATE site_images SET image_path = NULL WHERE slot = :slot', [':slot' => 'skin']);

        (new SiteMedia())->install($this->db);

        self::assertNull($this->db->scalar('SELECT image_path FROM site_images WHERE slot = :slot', [':slot' => 'skin']));
    }

    public function testACategoryIllustrationIsRepairedButAChosenOneIsKept(): void
    {
        // A chosen picture that is really there.
        $this->db->execute(
            'UPDATE service_categories SET image_path = NULL WHERE slug = :slug',
            [':slug' => 'nail'],
        );

        (new SiteMedia())->install($this->db);

        $chosen = (string) $this->db->scalar(
            'SELECT image_path FROM service_categories WHERE slug = :slug',
            [':slug' => 'nail'],
        );

        self::assertFileExists($this->uploads . '/' . $chosen);

        // A picture that was deleted from the server.
        $this->db->execute(
            'UPDATE service_categories SET image_path = :path WHERE slug = :slug',
            [':path' => 'ffffffffffffffffffffffff.jpg', ':slug' => 'nail'],
        );

        (new SiteMedia())->install($this->db);

        $repaired = (string) $this->db->scalar(
            'SELECT image_path FROM service_categories WHERE slug = :slug',
            [':slug' => 'nail'],
        );

        self::assertSame($chosen, $repaired);
        self::assertFileExists($this->uploads . '/' . $repaired);
    }

    public function testAPackagedPhotoFromBeforeTheCatalogueGetsLinkedToItsCategory(): void
    {
        $nail = $this->db->first('SELECT id FROM service_categories WHERE slug = :slug', [':slug' => 'nail']);

        if ($nail === null) {
            self::markTestSkipped('The service catalogue is not seeded.');
        }

        $media = new SiteMedia();
        $files = $media->copyFiles();
        $path = $files['nails'] ?? null;

        self::assertNotNull($path, 'The packaged manicure photo is available.');

        // The exact state after seeding before the category existed.
        $this->db->execute(
            'UPDATE gallery_items SET category_id = NULL WHERE image_path = :path',
            [':path' => $path],
        );

        $media->install($this->db);

        self::assertSame(
            (int) $nail['id'],
            (int) $this->db->scalar('SELECT category_id FROM gallery_items WHERE image_path = :path', [':path' => $path]),
            'The photo is filed under its category again, so its filter chip finds it.',
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function remember(string $sql, array $params): void
    {
        $row = $this->db->first($sql, $params);

        if ($row === null) {
            return;
        }

        $this->before[$params[':slot'] ?? $params[':slug']] = $row['image_path'] === null
            ? null
            : (string) $row['image_path'];
    }

    private function restore(string $slot, ?string $path): void
    {
        $this->db->execute(
            'UPDATE site_images SET image_path = :path WHERE slot = :slot',
            [':path' => $path, ':slot' => $slot],
        );
    }
}
