<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Defaults that decide who the application believes. These are not stylistic:
 * an empty proxy list would make every visitor share one address, and a missing
 * Cloudflare range would put the salon's own traffic in the wrong bucket.
 */
final class ConfigDefaultsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function appConfig(): array
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2) . '/config/app.php';

        return $config;
    }

    public function testTheProxyListDefaultsToCloudflareRanges(): void
    {
        $proxies = $this->appConfig()['trusted_proxies'];

        self::assertIsArray($proxies);
        self::assertContains('173.245.48.0/20', $proxies);
        self::assertContains('2606:4700::/32', $proxies);
        self::assertContains('172.64.0.0/13', $proxies);
        self::assertNotContains('0.0.0.0/0', $proxies);
    }

    public function testTheDatabaseClockFollowsTheAppTimeZone(): void
    {
        /** @var array<string, mixed> $database */
        $database = require dirname(__DIR__, 2) . '/config/database.php';

        self::assertSame('+03:00', $database['timezone']);
    }
}
