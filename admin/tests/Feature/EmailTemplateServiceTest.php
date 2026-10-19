<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Services\EmailTemplateCatalog;
use Wiz\Services\EmailTemplateService;

/**
 * The template workflow cloned from Bulbula.et: drafts are inert, publishing is
 * explicit, rollback is republishing, and nothing unsafe can be stored.
 */
final class EmailTemplateServiceTest extends TestCase
{
    private const HOST = 'https://wiz.test';

    private Database $db;

    private EmailTemplateService $templates;

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

        $this->db->execute('DELETE FROM email_template_active');
        $this->db->execute('DELETE FROM email_template_revisions');

        $this->templates = new EmailTemplateService($this->db, self::HOST);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->execute('DELETE FROM email_template_active');
            $this->db->execute('DELETE FROM email_template_revisions');
        }
    }

    public function testEveryTypeHasAUsableInitialDesignThatStaysInertUntilPublished(): void
    {
        foreach (array_keys(EmailTemplateCatalog::LABELS) as $key) {
            $initial = EmailTemplateCatalog::initial($key);
            $id = $this->templates->saveDraft($key, $initial['subject'], $initial['html'], $initial['text'], '{"pages":[]}', 1);

            self::assertGreaterThan(0, $id, $key);
            self::assertSame(0, $this->templates->activeId($key), $key);
        }

        self::assertCount(count(EmailTemplateCatalog::LABELS), $this->templates->list());
    }

    public function testDraftDoesNotGoLiveUntilPublishedAndOlderVersionCanBeRestored(): void
    {
        $key = 'booking_confirmed';
        $fallback = ['subject' => 'Original', 'html' => 'Original HTML', 'text' => 'Original text'];
        $first = EmailTemplateCatalog::initial($key);
        $id1 = $this->templates->saveDraft($key, $first['subject'], $first['html'], $first['text'], '{"pages":[]}', 42);

        // Nothing published yet: the caller's own fallback is used.
        self::assertSame($fallback, $this->templates->render($key, ['message' => 'Hello'], $fallback));

        $this->templates->publish($key, $id1);
        $live = $this->templates->render($key, ['message' => 'Hello Hanna'], $fallback);

        self::assertStringContainsString('Hello Hanna', $live['html']);
        self::assertStringContainsString('Hello Hanna', $live['text']);
        self::assertStringNotContainsString('{{message}}', $live['html']);

        $id2 = $this->templates->saveDraft(
            $key,
            'Updated subject',
            '<p>Hi {{name}} — {{message}}</p>',
            'Hi {{name}} — {{message}}',
            '{"pages":[]}',
            42,
        );

        // Saving a draft changed nothing that is live.
        self::assertSame($id1, $this->templates->activeId($key));

        $this->templates->publish($key, $id2);
        self::assertSame('Updated subject', $this->templates->render($key, ['message' => 'x', 'name' => 'Hanna'], $fallback)['subject']);

        // Rollback is publishing the older revision again.
        $this->templates->publish($key, $id1);
        self::assertSame($id1, $this->templates->activeId($key));
    }

    public function testValuesAreEscapedInHtmlButNotInPlainText(): void
    {
        $key = 'booking_requested';
        $id = $this->templates->saveDraft(
            $key,
            'Subject',
            '<p>{{message}}</p><p>{{name}}</p>',
            '{{message}} {{name}}',
            '{"pages":[]}',
            1,
        );
        $this->templates->publish($key, $id);

        $out = $this->templates->render($key, ['message' => 'Booking <b>moved</b>', 'name' => '<script>alert(1)</script>'], ['subject' => 'x', 'html' => 'x', 'text' => 'x']);

        self::assertStringNotContainsString('<script>', $out['html']);
        self::assertStringContainsString('&lt;script&gt;', $out['html']);
        self::assertStringContainsString('<script>', $out['text']);
    }

    public function testAnUnknownPlaceholderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft('booking_confirmed', 'Hi {{secret}}', '<p>{{message}}</p>', '{{message}}', '{"pages":[]}', 1);
    }

    public function testBothVersionsMustCarryTheRequiredPlaceholder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft('booking_confirmed', 'Subject', '<p>No placeholder here</p>', '{{message}}', '{"pages":[]}', 1);
    }

    public function testActiveContentIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft(
            'booking_confirmed',
            'Subject',
            '<p onclick="steal()">{{message}}</p>',
            '{{message}}',
            '{"pages":[]}',
            1,
        );
    }

    public function testALinkMustPointAtTheSalonsOwnHost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft(
            'booking_confirmed',
            'Subject',
            '<p>{{message}}</p><a href="https://elsewhere.test/x">Open</a>',
            '{{message}}',
            '{"pages":[]}',
            1,
        );
    }

    public function testTheSubjectMustFitOnOneLine(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft('booking_confirmed', "Two\nLines", '<p>{{message}}</p>', '{{message}}', '{"pages":[]}', 1);
    }

    public function testAnEmptyDesignIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->templates->saveDraft('booking_confirmed', 'Subject', '   ', '{{message}}', '{"pages":[]}', 1);
    }

    public function testAnUnpublishedTypeFallsBackToTheCaller(): void
    {
        $fallback = ['subject' => 'Fallback', 'html' => '<p>Fallback</p>', 'text' => 'Fallback'];

        self::assertSame($fallback, $this->templates->render('message_received', ['message' => 'x'], $fallback));
    }
}
