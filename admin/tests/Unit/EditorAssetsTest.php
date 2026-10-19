<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The studio is built elsewhere and committed; these checks are what stops a
 * rebuild from quietly reintroducing a CDN dependency or a fonts path the asset
 * route cannot serve.
 */
final class EditorAssetsTest extends TestCase
{
    private function asset(string $name): string
    {
        return dirname(__DIR__, 2) . '/public/assets/' . $name;
    }

    public function testTheBundleIsPresentAndSelfHostsItsIcons(): void
    {
        $js = $this->asset('email-editor.js');

        self::assertFileExists($js);
        self::assertGreaterThan(500_000, (int) filesize($js));

        $source = (string) file_get_contents($js);

        // Our override, not the library default. The default CDN URL is still
        // compiled into the bundle, so its presence proves nothing either way.
        self::assertStringContainsString('/admin/assets/font-awesome.min.css', $source);
        self::assertFileExists($this->asset('font-awesome.min.css'));
    }

    public function testTheIconStylesheetPointsAtFilesTheAssetRouteCanServe(): void
    {
        $css = $this->asset('font-awesome.min.css');

        self::assertFileExists($css);

        $source = (string) file_get_contents($css);

        // Flat paths only: /admin/assets/{file} serves basenames, not subfolders.
        self::assertStringContainsString("url('fontawesome-webfont.woff2", $source);
        self::assertStringNotContainsString("url('../fonts/", $source);

        foreach (['fontawesome-webfont.woff2', 'fontawesome-webfont.woff', 'fontawesome-webfont.ttf'] as $font) {
            self::assertFileExists($this->asset($font), $font . ' is missing from public/assets');
        }
    }
}
