<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Csrf;
use Wiz\Core\Request;
use Wiz\Tests\Support\ArraySessionStore;

final class CsrfTest extends TestCase
{
    private ArraySessionStore $session;

    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->session = new ArraySessionStore();
        $this->csrf = new Csrf($this->session);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function post(array $body, array $headers = []): Request
    {
        return new Request('POST', '/admin/login', [], $body, $headers);
    }

    public function testIssuesATokenOfSufficientLength(): void
    {
        $token = $this->csrf->token();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testReturnsTheSameTokenForTheWholeSession(): void
    {
        self::assertSame($this->csrf->token(), $this->csrf->token());
    }

    public function testAcceptsTheMatchingToken(): void
    {
        $token = $this->csrf->token();

        self::assertTrue($this->csrf->verify($this->post(['_token' => $token])));
    }

    public function testRejectsADifferentToken(): void
    {
        $this->csrf->token();

        self::assertFalse($this->csrf->verify($this->post(['_token' => str_repeat('a', 64)])));
    }

    public function testRejectsARequestWithNoToken(): void
    {
        $this->csrf->token();

        self::assertFalse($this->csrf->verify($this->post([])));
    }

    public function testRejectsEverythingWhenTheSessionHasNoToken(): void
    {
        self::assertFalse($this->csrf->verify($this->post(['_token' => str_repeat('a', 64)])));
    }

    public function testAcceptsATokenSentInTheHeader(): void
    {
        $token = $this->csrf->token();

        self::assertTrue($this->csrf->verify($this->post([], ['HTTP_X_CSRF_TOKEN' => $token])));
    }

    public function testRenderingTheFieldEscapesTheToken(): void
    {
        $field = $this->csrf->field();

        self::assertStringContainsString('name="_token"', $field);
        self::assertStringContainsString($this->csrf->token(), $field);
        self::assertStringNotContainsString('<script', $field);
    }

    public function testRotatingInvalidatesTheOldToken(): void
    {
        $original = $this->csrf->token();
        $this->csrf->rotate();
        $rotated = $this->csrf->token();

        self::assertNotSame($original, $rotated);
        self::assertFalse($this->csrf->verify($this->post(['_token' => $original])));
        self::assertTrue($this->csrf->verify($this->post(['_token' => $rotated])));
    }
}
