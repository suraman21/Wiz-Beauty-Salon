<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Router;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testMatchesALiteralPath(): void
    {
        $handler = static fn (): string => 'ok';
        $this->router->get('/admin/login', $handler);

        $match = $this->router->match('GET', '/admin/login');

        self::assertNotNull($match);
        self::assertSame($handler, $match['handler']);
        self::assertSame([], $match['params']);
    }

    public function testIgnoresATrailingSlash(): void
    {
        $this->router->get('/admin', static fn (): string => 'dashboard');

        self::assertNotNull($this->router->match('GET', '/admin/'));
    }

    public function testCapturesNamedParameters(): void
    {
        $this->router->get('/admin/services/{id}/edit', static fn (): string => 'edit');

        $match = $this->router->match('GET', '/admin/services/42/edit');

        self::assertNotNull($match);
        self::assertSame(['id' => '42'], $match['params']);
    }

    public function testDoesNotMatchAcrossSlashBoundaries(): void
    {
        $this->router->get('/admin/assets/{file}', static fn (): string => 'asset');

        self::assertNull($this->router->match('GET', '/admin/assets/css/admin.css'));
    }

    public function testReturnsNullForAnUnknownPath(): void
    {
        $this->router->get('/admin', static fn (): string => 'dashboard');

        self::assertNull($this->router->match('GET', '/nope'));
    }

    public function testChecksTheMethodBeforeThePath(): void
    {
        $this->router->post('/admin/login', static fn (): string => 'submit');

        self::assertNull($this->router->match('GET', '/admin/login'));
        self::assertSame(['POST'], $this->router->allowedMethods('/admin/login'));
    }

    public function testFirstRegisteredRouteWins(): void
    {
        $this->router->get('/admin/assets/{file}', static fn (): string => 'catch-all');
        $this->router->get('/admin/assets/admin.css', static fn (): string => 'specific');

        $match = $this->router->match('GET', '/admin/assets/admin.css');

        self::assertNotNull($match);
        self::assertSame(['file' => 'admin.css'], $match['params']);
    }

    public function testKeepsMiddlewareInRegistrationOrder(): void
    {
        $this->router->get('/admin', static fn (): string => 'dashboard', ['auth', 'admin']);

        $match = $this->router->match('GET', '/admin');

        self::assertNotNull($match);
        self::assertSame(['auth', 'admin'], $match['middleware']);
    }

    public function testBuildsAUrlForANamedRoute(): void
    {
        $this->router->get('/admin/bookings/{id}', static fn (): string => 'show', [], 'bookings.show');

        self::assertSame('/admin/bookings/7', $this->router->url('bookings.show', ['id' => 7]));
    }

    public function testThrowsForAnUnknownRouteName(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->router->url('missing.route');
    }
}
