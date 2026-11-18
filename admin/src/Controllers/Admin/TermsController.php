<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Auth;
use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\TermsRepository;
use Wiz\Services\TermsService;
use Wiz\Support\ChangePolicy;
use Wiz\Support\Slug;

/**
 * The booking terms, edited in place.
 *
 * One screen, because the document is one screen to a customer: the sections in
 * the order they are published, each clause editable in both languages where it
 * sits. Nothing is hidden behind a second page — a person changing clause 3.2
 * should be able to see 3.1 and 3.3 while they do it.
 *
 * Three placeholders (see TermsService) keep the live parts live: the change
 * window, its Amharic twin, and the salon's summary sentence are resolved when
 * the document is served, not typed here.
 */
final class TermsController
{
    private const MAX_TITLE = 160;

    private const MAX_CLAUSE = 2000;

    public function __construct(
        private readonly View $view,
        private readonly TermsRepository $terms,
        private readonly TermsService $service,
        private readonly ChangePolicy $policy,
        private readonly ActivityLogRepository $activity,
        private readonly Auth $auth,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $bySection = [];

        foreach ($this->terms->clauses() as $clause) {
            $bySection[(int) $clause['section_id']][] = $clause;
        }

        $sections = [];

        foreach ($this->terms->sections() as $section) {
            $section['clauses'] = $bySection[(int) $section['id']] ?? [];

            $sections[] = $section;
        }

        return $this->view->render('admin/terms/index', [
            'title' => 'Booking terms',
            'heading' => 'Booking terms',
            'subheading' => 'The page a customer agrees to at /booking/information, in English and Amharic. Changes appear on the website as soon as you save.',
            'active' => 'terms',
            'sections' => $sections,
            'tokens' => $this->service->tokenValues(),
            'policy' => $this->policy,
            'updatedOn' => $this->service->updatedOn(),
            'previewUrl' => '/booking/information',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function storeSection(Request $request, array $params = []): Response
    {
        $data = $this->validateText($request, 'title_en', 'title_am', self::MAX_TITLE, 'heading');

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/terms');
        }

        $slug = Slug::unique(
            Slug::make($data['en'], 'section'),
            fn (string $candidate): bool => $this->terms->slugExists($candidate),
        );

        $id = $this->terms->createSection($slug, $data['en'], $data['am']);

        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.section.created',
            'terms_section',
            $id,
            ['slug' => $slug],
            $request->ip(),
        );

        $this->session->flash('success', sprintf('“%s” was added. Add its first clause below.', $data['en']));

        throw new RedirectException('/admin/terms#' . $slug);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateSection(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $section = $this->terms->findSection($id);

        if ($section === null) {
            throw HttpException::notFound('That section no longer exists.');
        }

        $data = $this->validateText($request, 'title_en', 'title_am', self::MAX_TITLE, 'heading');

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/terms#' . (string) $section['slug']);
        }

        $this->terms->updateSection($id, $data['en'], $data['am']);
        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.section.updated',
            'terms_section',
            $id,
            [],
            $request->ip(),
        );

        $this->session->flash('success', 'The heading was saved. Its address on the page has not changed.');

        throw new RedirectException('/admin/terms#' . (string) $section['slug']);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroySection(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $section = $this->terms->findSection($id);

        if ($section === null) {
            throw HttpException::notFound('That section no longer exists.');
        }

        $clauses = $this->terms->clauseCount($id);

        $this->terms->deleteSection($id);
        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.section.deleted',
            'terms_section',
            $id,
            ['slug' => (string) $section['slug'], 'clauses' => $clauses],
            $request->ip(),
        );

        $this->session->flash(
            'success',
            sprintf('“%s” and its %d clause(s) were removed from the terms.', (string) $section['title_en'], $clauses),
        );

        throw new RedirectException('/admin/terms');
    }

    /**
     * @param array<string, string> $params
     */
    public function moveSection(Request $request, array $params = []): Response
    {
        $this->terms->move((int) ($params['id'] ?? 0), $request->string('direction') === 'down' ? 'down' : 'up');
        $this->service->touch();

        throw new RedirectException('/admin/terms');
    }

    /**
     * @param array<string, string> $params
     */
    public function storeClause(Request $request, array $params = []): Response
    {
        $sectionId = (int) $request->input('section_id', 0);
        $section = $this->terms->findSection($sectionId);

        if ($section === null) {
            throw HttpException::notFound('That section no longer exists.');
        }

        $data = $this->validateText($request, 'body_en', 'body_am', self::MAX_CLAUSE, 'clause');

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/terms#' . (string) $section['slug']);
        }

        $id = $this->terms->createClause($sectionId, $data['en'], $data['am']);

        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.clause.created',
            'terms_clause',
            $id,
            ['section' => (string) $section['slug']],
            $request->ip(),
        );

        $this->session->flash('success', 'The clause was added to the end of this section.');

        throw new RedirectException('/admin/terms#' . (string) $section['slug']);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateClause(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $clause = $this->terms->findClause($id);

        if ($clause === null) {
            throw HttpException::notFound('That clause no longer exists.');
        }

        $section = $this->terms->findSection((int) $clause['section_id']);
        $anchor = $section === null ? '' : '#' . (string) $section['slug'];

        $data = $this->validateText($request, 'body_en', 'body_am', self::MAX_CLAUSE, 'clause');

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/terms' . $anchor);
        }

        $this->terms->updateClause($id, $data['en'], $data['am']);
        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.clause.updated',
            'terms_clause',
            $id,
            [],
            $request->ip(),
        );

        $this->session->flash('success', 'The clause was saved, in both languages.');

        throw new RedirectException('/admin/terms' . $anchor);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroyClause(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $clause = $this->terms->findClause($id);

        if ($clause === null) {
            throw HttpException::notFound('That clause no longer exists.');
        }

        $section = $this->terms->findSection((int) $clause['section_id']);

        $this->terms->deleteClause($id);
        $this->service->touch();
        $this->activity->record(
            $this->auth->id(),
            'terms.clause.deleted',
            'terms_clause',
            $id,
            [],
            $request->ip(),
        );

        $this->session->flash('success', 'The clause was removed. The rest of the section renumbered itself.');

        throw new RedirectException('/admin/terms' . ($section === null ? '' : '#' . (string) $section['slug']));
    }

    /**
     * @param array<string, string> $params
     */
    public function moveClause(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $clause = $this->terms->findClause($id);

        $this->terms->moveClause($id, $request->string('direction') === 'down' ? 'down' : 'up');
        $this->service->touch();

        $section = $clause === null ? null : $this->terms->findSection((int) $clause['section_id']);

        throw new RedirectException('/admin/terms' . ($section === null ? '' : '#' . (string) $section['slug']));
    }

    /**
     * Both languages or neither.
     *
     * A clause that exists in English and not in Amharic shows as a gap to half
     * the customers, so the editor refuses it rather than storing it. The one
     * exception would be a heading, and a heading in one language is just as
     * broken.
     *
     * @return array{en: string, am: string}|string
     */
    private function validateText(Request $request, string $enField, string $amField, int $max, string $label): array|string
    {
        $validator = new Validator(
            [
                'en' => $request->string($enField),
                'am' => $request->string($amField),
            ],
            [
                'en' => 'required|max:' . $max,
                'am' => 'required|max:' . $max,
            ],
            [
                'en' => 'The English ' . $label,
                'am' => 'The Amharic ' . $label,
            ],
        );

        if (!$validator->validate()) {
            return (string) $validator->firstError() . ' Both languages are needed — the page shows one or the other.';
        }

        $input = $validator->validated();

        return [
            'en' => trim((string) $input['en']),
            'am' => trim((string) $input['am']),
        ];
    }

}
