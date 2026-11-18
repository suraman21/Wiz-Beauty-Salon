<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Controllers\Api\ContentApiController;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Core\Request;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Repositories\SiteImageRepository;
use Wiz\Repositories\TermsRepository;
use Wiz\Support\SalonDetails;
use Wiz\Services\TermsService;
use Wiz\Support\ChangePolicy;

/**
 * The booking terms as content.
 *
 * The document used to be compiled into the site, so a wording change needed a
 * deploy. It now lives in two tables the panel edits, which moves the risk: the
 * page a customer agrees to must still say the same thing, in both languages,
 * and it must still be impossible for the text to promise a change window the
 * booking form would refuse.
 *
 * That last part is what most of these tests are about. The clause holds
 * {{window}}, the salon's setting holds the number, and TermsService joins them
 * at the point the page is served.
 */
final class TermsTest extends TestCase
{
    private Database $db;

    private TermsRepository $terms;

    private SettingsRepository $settings;

    private TermsService $service;

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

        // A known starting point, whatever the migration seeded: every test
        // builds the document it asserts on, so the suite does not depend on
        // the fixture rows surviving another test.
        $this->db->execute('DELETE FROM terms_sections');
        $this->db->execute('DELETE FROM settings WHERE setting_key IN (:updated, :window)', [
            ':updated' => TermsService::UPDATED_SETTING,
            ':window' => ChangePolicy::SETTING,
        ]);

        $this->terms = new TermsRepository($this->db);
        $this->settings = new SettingsRepository($this->db);
        $this->service = new TermsService(
            $this->terms,
            $this->settings,
            new ChangePolicy($this->settings),
        );
    }

    private function setWindow(int $hours): void
    {
        $this->db->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :updated',
            [
                ':key' => ChangePolicy::SETTING,
                ':value' => (string) $hours,
                ':updated' => (string) $hours,
            ],
        );

        // The service holds its own copy of the policy, which reads the setting
        // through a repository that caches — rebuild both.
        $settings = new SettingsRepository($this->db);
        $this->service = new TermsService($this->terms, $settings, new ChangePolicy($settings));
    }

    private function section(string $slug = 'window', string $en = 'Changing your booking', string $am = 'ቀጠሮ መቀየር'): int
    {
        return $this->terms->createSection($slug, $en, $am);
    }

    // ------------------------------------------------------------- the document

    public function testAnInstallationWithNoSectionsHasPublishedNothing(): void
    {
        self::assertFalse($this->service->isPublished());
        self::assertSame([], $this->service->document()['sections']);
    }

    public function testTheDocumentIsSectionsOfClausesInTheOrderStaffArranged(): void
    {
        $first = $this->section('about', 'About these terms', 'ስለ እነዚህ ደንቦች');
        $second = $this->section('late', 'Late arrivals', 'ዘግይቶ መምጣት');

        $this->terms->createClause($first, 'First clause.', 'የመጀመሪያ አንቀጽ።');
        $this->terms->createClause($first, 'Second clause.', 'ሁለተኛ አንቀጽ።');
        $this->terms->createClause($second, 'Call us.', 'ይደውሉልን።');

        $document = $this->service->document();

        self::assertTrue($this->service->isPublished());
        self::assertSame(['about', 'late'], array_column($document['sections'], 'id'));
        self::assertCount(2, $document['sections'][0]['clauses']);
        self::assertSame(
            ['First clause.', 'Second clause.'],
            array_column($document['sections'][0]['clauses'], 'en'),
        );
        // Both languages, always: the page toggles between them.
        self::assertSame('ሁለተኛ አንቀጽ።', $document['sections'][0]['clauses'][1]['am']);
        self::assertSame('ዘግይቶ መምጣት', $document['sections'][1]['title']['am']);
    }

    // -------------------------------------------------------------- live tokens

    public function testTheChangeWindowIsFilledInFromTheSalonsOwnSetting(): void
    {
        $this->setWindow(48);
        $id = $this->section();
        $this->terms->createClause(
            $id,
            'You may change or cancel {{window}}.',
            'ቀጠሮዎን {{window_am}} መቀየር ወይም መሰረዝ ይችላሉ።',
        );

        $clause = $this->service->document()['sections'][0]['clauses'][0];

        self::assertSame('You may change or cancel 48 hours before the appointment.', $clause['en']);
        self::assertSame('ቀጠሮዎን ከቀጠሮው በፊት 48 hours መቀየር ወይም መሰረዝ ይችላሉ።', $clause['am']);
        self::assertStringNotContainsString('{{', $clause['en']);
        self::assertStringNotContainsString('{{', $clause['am']);
    }

    public function testNoCutOffReadsAsNoCutOff(): void
    {
        $this->setWindow(0);
        $id = $this->section();
        $this->terms->createClause($id, 'Change {{window}}.', '{{window_am}} ይቀይሩ።');

        $clause = $this->service->document()['sections'][0]['clauses'][0];

        self::assertSame('Change any time before the appointment starts.', $clause['en']);
        self::assertStringContainsString('ቀጠሮው ከመጀመሩ በፊት', $clause['am']);
    }

    /**
     * The whole point of {{policy}}: the sentence the booking form promises and
     * the sentence the terms print are the same sentence, from one setting.
     */
    public function testTheSalonsSummarySentenceIsNeverTypedTwice(): void
    {
        $this->setWindow(24);
        $id = $this->section();
        $this->terms->createClause($id, 'Our rule: {{policy}}', 'ደንባችን፦ {{policy}}');

        $document = $this->service->document();

        self::assertSame(
            'Our rule: ' . (new ChangePolicy($this->settings))->summary(),
            $document['sections'][0]['clauses'][0]['en'],
        );
        self::assertSame(
            'ደንባችን፦ ' . (new ChangePolicy($this->settings))->summary(),
            $document['sections'][0]['clauses'][0]['am'],
        );
    }

    public function testTheEditorIsToldWhatEachPlaceholderIsWorth(): void
    {
        $this->setWindow(12);

        $values = $this->service->tokenValues();

        self::assertSame('12 hours before the appointment', $values['{{window}}']);
        self::assertStringContainsString('12', $values['{{window_am}}']);
        self::assertStringContainsString('12 hours', $values['{{policy}}']);
    }

    // -------------------------------------------------------------- rearranging

    public function testMovingASectionChangesTheDocumentOrder(): void
    {
        $this->section('about', 'About', 'ስለ');
        $this->section('late', 'Late', 'ዘግይቶ');

        $late = $this->terms->sections()[1];

        self::assertTrue($this->terms->move((int) $late['id'], 'up'));
        self::assertSame(['late', 'about'], array_column($this->service->document()['sections'], 'id'));
    }

    /**
     * Clause 2 of "Late arrivals" must never trade places with a clause in
     * another section: the arrows move a line inside its own section.
     */
    public function testMovingAClauseStaysInsideItsOwnSection(): void
    {
        $late = $this->section('late', 'Late', 'ዘግይቶ');
        $health = $this->section('health', 'Health', 'ጤና');

        $this->terms->createClause($late, 'Late one.', 'አንድ።');
        $this->terms->createClause($late, 'Late two.', 'ሁለት።');
        $this->terms->createClause($health, 'Health one.', 'ጤና አንድ።');

        $second = $this->terms->clauses()[1];

        self::assertTrue($this->terms->moveClause((int) $second['id'], 'up'));

        $document = $this->service->document();

        self::assertSame(['Late two.', 'Late one.'], array_column($document['sections'][0]['clauses'], 'en'));
        self::assertSame(['Health one.'], array_column($document['sections'][1]['clauses'], 'en'));
    }

    public function testTheFirstClauseCannotBeMovedAboveNothing(): void
    {
        $id = $this->section();
        $this->terms->createClause($id, 'Only one.', 'አንድ ብቻ።');

        $first = $this->terms->clauses()[0];

        self::assertFalse($this->terms->moveClause((int) $first['id'], 'up'));
        self::assertSame('Only one.', $this->terms->clauses()[0]['body_en']);
    }

    // -------------------------------------------------------------- removing

    public function testRemovingASectionTakesItsClausesWithIt(): void
    {
        $id = $this->section();
        $this->terms->createClause($id, 'One.', 'አንድ።');
        $this->terms->createClause($id, 'Two.', 'ሁለት።');

        $this->terms->deleteSection($id);

        self::assertSame(0, $this->terms->sectionCount());
        self::assertSame(0, (int) $this->db->scalar('SELECT COUNT(*) FROM terms_clauses'));
    }

    public function testRemovingAClauseLeavesTheRestNumberedInOrder(): void
    {
        $id = $this->section();
        $this->terms->createClause($id, 'One.', 'አንድ።');
        $middle = $this->terms->createClause($id, 'Two.', 'ሁለት።');
        $this->terms->createClause($id, 'Three.', 'ሦስት።');

        $this->terms->deleteClause($middle);

        $document = $this->service->document();

        self::assertSame(['One.', 'Three.'], array_column($document['sections'][0]['clauses'], 'en'));
    }

    // -------------------------------------------------------------- identity

    /**
     * The anchor is generated once. Rewording a heading must not break a link a
     * customer bookmarked or an email quoted.
     */
    public function testRewordingAHeadingKeepsItsAnchor(): void
    {
        $id = $this->section('window', 'Changing your booking', 'ቀጠሮ መቀየር');

        $this->terms->updateSection($id, 'Changing or cancelling', 'መቀየር ወይም መሰረዝ');

        $document = $this->service->document();

        self::assertSame('window', $document['sections'][0]['id']);
        self::assertSame('Changing or cancelling', $document['sections'][0]['title']['en']);
        self::assertSame('መቀየር ወይም መሰረዝ', $document['sections'][0]['title']['am']);
    }

    public function testEditingAClauseSavesBothLanguages(): void
    {
        $id = $this->section();
        $clauseId = $this->terms->createClause($id, 'Old.', 'አሮጌ።');

        $this->terms->updateClause($clauseId, 'New English.', 'አዲስ አማርኛ።');

        $clause = $this->service->document()['sections'][0]['clauses'][0];

        self::assertSame('New English.', $clause['en']);
        self::assertSame('አዲስ አማርኛ።', $clause['am']);
    }

    // ---------------------------------------------------------------- the date

    public function testSavingTodayStampsTheDocument(): void
    {
        $this->service->touch();

        self::assertSame(date('Y-m-d'), $this->service->updatedOn());
        self::assertSame(date('Y-m-d'), $this->service->document()['updated_on']);
    }

    public function testADateThatWasNeverSetFallsBackToToday(): void
    {
        self::assertSame(date('Y-m-d'), $this->service->updatedOn());
    }

    // ------------------------------------------------------------- the endpoint

    /**
     * What the website actually receives. The panel is the only place the text
     * lives, so this answer has to carry everything the page renders: the anchor,
     * both headings, both clause bodies, the date, and no leftover placeholder.
     */
    private function api(): ContentApiController
    {
        $settings = new SettingsRepository($this->db);

        return new ContentApiController(
            new GalleryRepository($this->db),
            new ReviewRepository($this->db),
            new SiteImageRepository($this->db),
            new SalonDetails($settings),
            new BusinessHoursRepository($this->db),
            new ClosureRepository($this->db),
            new ChangePolicy($settings),
            new TermsService($this->terms, $settings, new ChangePolicy($settings)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function apiBody(): array
    {
        $response = $this->api()->terms(new Request('GET', '/api/v1/terms'));

        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testTheWebsiteReceivesTheWholeDocument(): void
    {
        $this->setWindow(24);
        $id = $this->section('window', 'Changing your booking', 'ቀጠሮ መቀየር');
        $this->terms->createClause($id, 'Free until {{window}}.', 'እስከ {{window_am}} ነፃ።');

        $body = $this->apiBody();

        self::assertTrue($body['published']);
        self::assertSame(date('Y-m-d'), $body['updated_on']);
        self::assertSame('window', $body['sections'][0]['id']);
        self::assertSame('Changing your booking', $body['sections'][0]['title']['en']);
        self::assertSame('ቀጠሮ መቀየር', $body['sections'][0]['title']['am']);
        self::assertSame(
            'Free until 24 hours before the appointment.',
            $body['sections'][0]['clauses'][0]['en'],
        );
        self::assertStringContainsString('እስከ', $body['sections'][0]['clauses'][0]['am']);
        self::assertStringNotContainsString(
            '{{',
            json_encode($body, JSON_THROW_ON_ERROR),
            'A placeholder reaching the website would be printed to a customer.',
        );
    }

    /**
     * An installation that has never opened the editor must not serve an empty
     * legal page: `published: false` is the site's cue to keep its bundled copy.
     */
    public function testAnEmptyPanelTellsTheWebsiteToUseItsOwnCopy(): void
    {
        $body = $this->apiBody();

        self::assertFalse($body['published']);
        self::assertNull($body['updated_on']);
        self::assertSame([], $body['sections']);
    }
}
