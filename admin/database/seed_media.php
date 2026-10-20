<?php
declare(strict_types=1);

/**
 * Puts the website's packaged pictures into the panel.
 *
 *   php database/seed_media.php
 *
 * The production migration does this once; this script is for a development
 * database that has been wiped, or for a salon that wants the shipped pictures
 * back after clearing them. It never overwrites: a slot with a picture, a
 * category with an illustration and a gallery with photos are all left alone.
 */

use Wiz\Core\Config;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Support\SiteMedia;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$config = new Config($root . '/config');
$db = new Database($config->file('database'));

$result = (new SiteMedia())->install($db);

fwrite(STDOUT, "Files available: {$result['files']}\n");
fwrite(STDOUT, "Image slots added: {$result['slots']}\n");
fwrite(STDOUT, "Gallery photos added: {$result['gallery']}\n");
fwrite(STDOUT, "Category illustrations set: {$result['categories']}\n");
