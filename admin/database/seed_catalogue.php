<?php
declare(strict_types=1);

/**
 * Fills the two tables the public booking page reads from.
 *
 *   php database/seed_catalogue.php
 *
 * The list comes from database/seeds/catalogue.json, which mirrors the service
 * catalogue the salon already publishes on the site. It is content, not schema,
 * so it is a seed rather than a migration: running it twice changes nothing,
 * and anything staff have edited in the panel is left alone.
 *
 * Prices are left empty on purpose — the salon has not published them, and the
 * booking page says "Price · to be confirmed" when a service has none.
 * Durations start at 60 minutes so the calendar has something to work with;
 * staff can correct each one on the Services screen.
 */

use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Support\Slug;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$config = new Config($root . '/config');
$db = new Database($config->file('database'));

$file = $root . '/database/seeds/catalogue.json';
$raw = file_get_contents($file);

if ($raw === false) {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}

/** @var list<array{id: string, name: string, amharic: string, items: list<string>}> $catalogue */
$catalogue = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

$defaultDuration = 60;
$categories = 0;
$services = 0;
$skipped = 0;

foreach ($catalogue as $order => $category) {
    $slug = (string) $category['id'];

    $existing = $db->first('SELECT id FROM service_categories WHERE slug = :slug', [':slug' => $slug]);

    if ($existing === null) {
        $db->insert('service_categories', [
            'name' => $category['name'],
            'slug' => $slug,
            'description' => $category['amharic'],
            'sort_order' => $order * 10,
            'is_active' => 1,
        ]);
        $categories++;
        $existing = $db->first('SELECT id FROM service_categories WHERE slug = :slug', [':slug' => $slug]);
    }

    $categoryId = $existing === null ? null : (int) $existing['id'];

    foreach ($category['items'] as $index => $name) {
        $serviceSlug = $slug . '-' . Slug::make($name);

        if ($db->first('SELECT id FROM services WHERE slug = :slug', [':slug' => $serviceSlug]) !== null) {
            $skipped++;
            continue;
        }

        $db->insert('services', [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $serviceSlug,
            'short_description' => null,
            'description' => null,
            'price_cents' => null,
            'duration_minutes' => $defaultDuration,
            'image_path' => null,
            'is_active' => 1,
            'is_featured' => 0,
            'sort_order' => $index * 10,
        ]);
        $services++;
    }
}

fwrite(STDOUT, "Categories added: {$categories}\n");
fwrite(STDOUT, "Services added: {$services}\n");
fwrite(STDOUT, "Already there: {$skipped}\n");
