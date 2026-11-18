<?php
declare(strict_types=1);

namespace Wiz\Support;

use Wiz\Core\Database;
use Wiz\Core\Env;

/**
 * The pictures the website shipped with, packaged with the application.
 *
 * They live here rather than in the migration so the same code can put them
 * back on a development machine (`php database/seed_media.php`) and on a fresh
 * production install (the migration calls it once). Everything is written
 * defensively: a slot that already has a picture, a category that already has
 * an illustration and a gallery that already has photos are all left alone.
 */
final class SiteMedia
{
    /** slot => [label, packaged file, alt text] */
    public const IMAGES = [
        'hero' => ['Home page hero', 'home-hero.jpg', 'Editorial beauty portrait'],
        'portrait' => ['Editorial portrait', 'hero-portrait.jpg', 'Editorial beauty portrait'],
        'space' => ['Salon ambience', 'salon-space.jpg', 'Salon-inspired interior'],
        'nails' => ['Manicure detail', 'nails-editorial.jpg', 'Manicure detail'],
        'hair' => ['Hair detail', 'hair-detail.jpg', 'Hair being styled'],
        'makeup' => ['Makeup portrait', 'makeup-editorial.jpg', 'Makeup portrait'],
        'skin' => ['Skin portrait', 'skin-editorial.jpg', 'Skincare portrait'],
        'before' => ['Before the change', 'transformation-before.jpg', 'Before a transformation'],
        'after' => ['After the change', 'transformation-after.jpg', 'After a transformation'],
    ];

    /** [slot, title, category slug] — the seven photos the gallery published. */
    public const GALLERY = [
        ['portrait', 'Soft volume', 'hair-styling'],
        ['nails', 'Quiet polish', 'nail'],
        ['makeup', 'Evening glow', 'make-up'],
        ['skin', 'Fresh skin', null],
        ['hair', 'Movement', 'hair-styling'],
        ['after', 'Long waves', 'hair-styling'],
        ['hero', 'Lilac mood', 'make-up'],
    ];

    /** category slug => the illustration it showed on the Services page. */
    public const CATEGORY_IMAGES = [
        'make-up' => 'makeup',
        'nail' => 'nails',
        'pedicure' => 'nails',
        'wax' => 'space',
        'eye-lash' => 'makeup',
        'mesh' => 'hair',
        'hair-treatment' => 'hair',
        'hair-cut' => 'hair',
        'hair-paint' => 'hair',
        'hair-styling' => 'portrait',
        'braids' => 'hair',
        'sewing' => 'hair',
        'ponytail' => 'portrait',
        'kids-braids' => 'space',
    ];

    /**
     * Copy the packaged files into the uploads folder and fill the empty rows.
     *
     * @return array{files: int, slots: int, gallery: int, categories: int}
     */
    public function install(Database $db): array
    {
        $files = $this->copyFiles();

        if ($files === []) {
            return ['files' => 0, 'slots' => 0, 'gallery' => 0, 'categories' => 0];
        }

        return [
            'files' => count($files),
            'slots' => $this->seedSiteImages($db, $files),
            'gallery' => $this->seedGallery($db, $files),
            'categories' => $this->seedCategoryImages($db, $files),
        ];
    }

    /**
     * Uploaded files must be named the way the upload route serves them:
     * 24 hex characters and an image extension. The name is derived from the
     * slot, so the same file is never written twice.
     *
     * @return array<string, string> slot => stored file name
     */
    public function copyFiles(): array
    {
        $source = dirname(__DIR__, 2) . '/database/seeds/media';
        $target = rtrim((string) Env::get('UPLOAD_PATH', dirname(__DIR__, 2) . '/storage/uploads'), '/');

        // A fresh server has no uploads folder yet. Returning early here used to
        // seed the database with paths to files that were never written.
        if (is_dir($source) && !is_dir($target)) {
            @mkdir($target, 0o755, true);
        }

        if (!is_dir($source) || !is_dir($target)) {
            return [];
        }

        $copied = [];

        foreach (self::IMAGES as $slot => [, $file]) {
            $from = $source . '/' . $file;

            if (!is_file($from)) {
                continue;
            }

            $name = substr(sha1('wiz-site-image:' . $slot), 0, 24) . '.jpg';
            $to = $target . '/' . $name;

            if (!is_file($to) && !copy($from, $to)) {
                continue;
            }

            @chmod($to, 0o644);
            $copied[$slot] = $name;
        }

        return $copied;
    }

    /**
     * @param array<string, string> $files
     */
    private function seedSiteImages(Database $db, array $files): int
    {
        $created = 0;
        $order = 0;

        foreach (self::IMAGES as $slot => [$label, , $alt]) {
            $order += 10;

            $existing = $db->first('SELECT id, image_path FROM site_images WHERE slot = :slot', [':slot' => $slot]);

            if ($existing !== null) {
                // An empty slot is a decision — the site falls back to the
                // packaged picture. A slot pointing at a file that is no longer
                // there is a broken picture, so it is put back.
                if ($this->isMissing($existing['image_path'] ?? null)) {
                    $db->execute(
                        'UPDATE site_images SET image_path = :path WHERE id = :id',
                        [':path' => $files[$slot] ?? null, ':id' => (int) $existing['id']],
                    );
                }

                continue;
            }

            $db->insert('site_images', [
                'slot' => $slot,
                'label' => $label,
                'image_path' => $files[$slot] ?? null,
                'alt' => $alt,
                'sort_order' => $order,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param array<string, string> $files
     */
    private function seedGallery(Database $db, array $files): int
    {
        $created = 0;

        if ((int) $db->scalar('SELECT COUNT(*) FROM gallery_items') === 0) {
            $order = 0;

            foreach (self::GALLERY as [$slot, $title, $categorySlug]) {
                $order += 10;

                if (!isset($files[$slot])) {
                    continue;
                }

                $db->insert('gallery_items', [
                    'image_path' => $files[$slot],
                    'category_id' => $this->categoryId($db, $categorySlug),
                    'title' => $title,
                    'caption' => null,
                    'is_published' => 1,
                    'sort_order' => $order,
                ]);

                $created++;
            }
        }

        $this->linkGalleryCategories($db, $files);

        return $created;
    }

    /**
     * A photo seeded before the catalogue existed has no category, so it never
     * shows under a filter chip. Now that the categories are there, link the
     * packaged photos — only the empty ones, so staff keep their own filing.
     *
     * @param array<string, string> $files
     */
    private function linkGalleryCategories(Database $db, array $files): void
    {
        foreach (self::GALLERY as [$slot, , $categorySlug]) {
            if (!isset($files[$slot])) {
                continue;
            }

            $category = $this->categoryId($db, $categorySlug);

            if ($category === null) {
                continue;
            }

            $db->execute(
                'UPDATE gallery_items SET category_id = :category
                  WHERE image_path = :path AND category_id IS NULL',
                [':category' => $category, ':path' => $files[$slot]],
            );
        }
    }

    private function categoryId(Database $db, ?string $slug): ?int
    {
        if ($slug === null) {
            return null;
        }

        $row = $db->first('SELECT id FROM service_categories WHERE slug = :slug', [':slug' => $slug]);

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @param array<string, string> $files
     */
    private function seedCategoryImages(Database $db, array $files): int
    {
        $updated = 0;

        foreach (self::CATEGORY_IMAGES as $slug => $slot) {
            if (!isset($files[$slot])) {
                continue;
            }

            $row = $db->first('SELECT id, image_path FROM service_categories WHERE slug = :slug', [':slug' => $slug]);

            if ($row === null) {
                continue;
            }

            $current = $row['image_path'] ?? null;

            if ($current !== null && $current !== '' && !$this->isMissing($current)) {
                continue;
            }

            $updated += $db->execute(
                'UPDATE service_categories SET image_path = :path WHERE id = :id',
                [':path' => $files[$slot], ':id' => (int) $row['id']],
            );
        }

        return $updated;
    }

    /**
     * True when a stored file name no longer has a file behind it. A null or
     * empty path is not "missing" — it means nothing was chosen.
     */
    private function isMissing(?string $stored): bool
    {
        if ($stored === null) {
            return false;
        }

        // An empty path is a leftover, not a decision — a slot is emptied by
        // setting it to NULL. Anything empty with a packaged file behind it is
        // put back.
        if ($stored === '') {
            return true;
        }

        $directory = rtrim((string) Env::get('UPLOAD_PATH', dirname(__DIR__, 2) . '/storage/uploads'), '/');

        return !is_file($directory . '/' . basename($stored));
    }
}
