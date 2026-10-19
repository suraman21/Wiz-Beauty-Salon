<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\RateLimitRepository;

/**
 * Fixed-window counters, against the real schema.
 *
 * The window is compared inside MySQL on purpose: the database and PHP can sit
 * in different time zones, and a PHP-side comparison makes an hour look like
 * three. This test pins that down.
 */
final class RateLimitTest extends TestCase
{
    private Database $db;
    private RateLimitRepository $limits;

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

        $this->db->execute("DELETE FROM rate_limits WHERE bucket LIKE 'phpunit-rate-%'");

        $this->limits = new RateLimitRepository($this->db);
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM rate_limits WHERE bucket LIKE 'phpunit-rate-%'");
    }

    public function testItAllowsExactlyTheLimit(): void
    {
        $bucket = 'phpunit-rate-mail';

        self::assertTrue($this->limits->attempt($bucket, 3, 60));
        self::assertTrue($this->limits->attempt($bucket, 3, 60));
        self::assertTrue($this->limits->attempt($bucket, 3, 60));
        self::assertFalse($this->limits->attempt($bucket, 3, 60));
    }

    public function testRemainingCountsDownWithoutCountingAnAttempt(): void
    {
        $bucket = 'phpunit-rate-left';

        self::assertSame(3, $this->limits->remaining($bucket, 3, 60));

        $this->limits->attempt($bucket, 3, 60);

        self::assertSame(2, $this->limits->remaining($bucket, 3, 60));
        self::assertSame(2, $this->limits->remaining($bucket, 3, 60));

        $this->limits->attempt($bucket, 3, 60);

        self::assertSame(1, $this->limits->remaining($bucket, 3, 60));
    }

    public function testAnExpiredWindowStartsAgain(): void
    {
        $bucket = 'phpunit-rate-expired';

        for ($i = 0; $i < 3; $i++) {
            $this->limits->attempt($bucket, 3, 60);
        }

        self::assertFalse($this->limits->attempt($bucket, 3, 60));

        // Two hours ago by the database's own clock.
        $this->db->execute(
            'UPDATE rate_limits SET window_start = (NOW() - INTERVAL 2 HOUR) WHERE bucket = :bucket',
            [':bucket' => $bucket],
        );

        self::assertSame(3, $this->limits->remaining($bucket, 3, 60));
        self::assertTrue($this->limits->attempt($bucket, 3, 60));
        self::assertSame(1, (int) $this->db->scalar(
            'SELECT hits FROM rate_limits WHERE bucket = :bucket',
            [':bucket' => $bucket],
        ));
    }
}
