<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wiz\Support\SignedLink;

final class SignedLinkTest extends TestCase
{
    private SignedLink $links;

    protected function setUp(): void
    {
        $this->links = new SignedLink(str_repeat('k', 64));
    }

    public function testRoundTrips(): void
    {
        $claim = $this->links->verify($this->links->issue('booking', 42));

        self::assertNotNull($claim);
        self::assertSame('booking', $claim['purpose']);
        self::assertSame(42, $claim['id']);
    }

    public function testIsUrlSafe(): void
    {
        $token = $this->links->issue('booking', 7);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);
    }

    public function testRejectsATamperedId(): void
    {
        $token = $this->links->issue('booking', 42);
        [$payload, $signature] = explode('.', $token);

        $forged = rtrim(strtr(base64_encode('booking:43:9999999999'), '+/', '-_'), '=');

        self::assertNull($this->links->verify($forged . '.' . $signature));
        self::assertNull($this->links->verify($payload . '.' . $signature . 'x'));
    }

    public function testRejectsATokenFromAnotherKey(): void
    {
        $other = new SignedLink(str_repeat('z', 64));

        self::assertNull($this->links->verify($other->issue('booking', 42)));
    }

    public function testRejectsAnExpiredToken(): void
    {
        $token = $this->links->issue('booking', 42, 1);

        sleep(2);

        self::assertNull($this->links->verify($token));
    }

    public function testRejectsRubbish(): void
    {
        foreach (['', 'x', 'a.b', '....', 'not-a-token'] as $token) {
            self::assertNull($this->links->verify($token), 'accepted: ' . $token);
        }
    }

    public function testRefusesToRunWithoutAKey(): void
    {
        $this->expectException(RuntimeException::class);

        new SignedLink('');
    }
}
