<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Request;

/**
 * The client IP decides who is rate limited and who is locked out, so the rules
 * for believing a forwarding header matter more than they look.
 */
final class RequestTrustTest extends TestCase
{
    /**
     * @param array<string, mixed> $server
     * @param list<string> $trusted
     */
    private function request(array $server, array $trusted = []): Request
    {
        return new Request('GET', '/', [], [], $server, [], $trusted);
    }

    public function testIgnoresForwardingHeadersFromAnUntrustedAddress(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            'HTTP_CF_CONNECTING_IP' => '5.6.7.8',
        ]);

        self::assertSame('203.0.113.9', $request->ip());
    }

    public function testBelievesCloudflareWhenTheProxyIsTrusted(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.44',
        ], ['10.0.0.0/8']);

        self::assertSame('198.51.100.7', $request->ip());
    }

    public function testTakesTheLeftmostForwardedAddress(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '10.1.2.3',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.2',
        ], ['10.0.0.0/8']);

        self::assertSame('198.51.100.7', $request->ip());
    }

    public function testMatchesAnExactAddress(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.16.0.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
        ], ['172.16.0.5']);

        self::assertSame('198.51.100.9', $request->ip());
    }

    public function testMatchesAnIpv6Range(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '2001:db8:abcd::1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.10',
        ], ['2001:db8:abcd::/48']);

        self::assertSame('198.51.100.10', $request->ip());
    }

    public function testAnAddressOutsideTheRangeIsNotATrustedProxy(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '192.0.2.50',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.11',
        ], ['10.0.0.0/8']);

        self::assertSame('192.0.2.50', $request->ip());
    }

    public function testADirectVisitorCanBeToldApartFromOthers(): void
    {
        self::assertTrue($this->request(['REMOTE_ADDR' => '192.0.2.50'])->trustsClientIp());
    }

    public function testAVisitorBehindAnUnconfiguredProxyCannot(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.68.1.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.12',
        ]);

        self::assertFalse($request->trustsClientIp());
    }

    public function testAVisitorBehindAConfiguredProxyCan(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.68.1.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.12',
        ], ['172.68.0.0/16']);

        self::assertTrue($request->trustsClientIp());
    }
}
