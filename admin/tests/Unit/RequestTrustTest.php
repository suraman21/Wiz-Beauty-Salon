<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Request;

/**
 * The client address decides who is rate-limited and what the audit trail
 * records, so a forwarding header is only believed from a configured proxy.
 */
final class RequestTrustTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    /**
     * @param array<string, string> $headers
     * @param list<string> $trusted
     */
    private function request(array $headers, array $trusted = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = $headers['REMOTE_ADDR'];

        foreach (['CF_CONNECTING_IP' => 'HTTP_CF_CONNECTING_IP', 'X_FORWARDED_FOR' => 'HTTP_X_FORWARDED_FOR'] as $key => $name) {
            unset($_SERVER[$name]);

            if (isset($headers[$key])) {
                $_SERVER[$name] = $headers[$key];
            }
        }

        return Request::capture($trusted);
    }

    public function testAForgedHeaderFromAnUntrustedPeerIsIgnored(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '203.0.113.9',
            'CF_CONNECTING_IP' => '198.51.100.7',
            'X_FORWARDED_FOR' => '198.51.100.8, 10.0.0.1',
        ]);

        self::assertSame('203.0.113.9', $request->ip());
        self::assertFalse($request->trustsClientIp());
    }

    public function testAConfiguredProxyMaySupplyTheClientAddress(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.70.1.2',
            'CF_CONNECTING_IP' => '198.51.100.7',
        ], ['172.64.0.0/13']);

        self::assertSame('198.51.100.7', $request->ip());
        self::assertTrue($request->trustsClientIp());
    }

    public function testTheLeftmostForwardedAddressIsTheClient(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '10.1.1.1',
            'X_FORWARDED_FOR' => '198.51.100.8, 10.0.0.1, 10.0.0.2',
        ], ['10.0.0.0/8']);

        self::assertSame('198.51.100.8', $request->ip());
    }

    public function testAnExactProxyAddressIsAlsoTrusted(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '192.0.2.10',
            'CF_CONNECTING_IP' => '198.51.100.9',
        ], ['192.0.2.10']);

        self::assertSame('198.51.100.9', $request->ip());
    }

    public function testIPv6ProxiesMatchByPrefix(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '2606:4700:10::6816:1',
            'CF_CONNECTING_IP' => '2001:db8::1',
        ], ['2606:4700::/32']);

        self::assertSame('2001:db8::1', $request->ip());
    }

    public function testAPeerOutsideTheRangeIsNotTrusted(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.63.0.1',
            'CF_CONNECTING_IP' => '198.51.100.7',
        ], ['172.64.0.0/13']);

        self::assertSame('172.63.0.1', $request->ip());
    }

    public function testWithoutConfigurationNoHeaderIsBelieved(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '203.0.113.9',
            'CF_CONNECTING_IP' => '198.51.100.7',
        ]);

        self::assertSame('203.0.113.9', $request->ip());
    }

    public function testAHeaderWithGarbageFallsBackToThePeer(): void
    {
        $request = $this->request([
            'REMOTE_ADDR' => '172.70.1.2',
            'CF_CONNECTING_IP' => 'not-an-address',
        ], ['172.64.0.0/13']);

        self::assertSame('172.70.1.2', $request->ip());
    }
}
