<?php
declare(strict_types=1);

namespace Wiz\Services;

use Wiz\Repositories\SettingsRepository;
use Wiz\Repositories\TermsRepository;
use Wiz\Support\ChangePolicy;

/**
 * The booking terms as one document, ready to be read.
 *
 * The panel stores sections and clauses; the website and the panel's own preview
 * both want the finished article, in both languages, with the live parts already
 * filled in. That assembly lives here so there is exactly one version of it.
 *
 * Three placeholders are allowed in the text, and they are the whole reason this
 * class exists:
 *
 *   {{window}}      "24 hours before the appointment" — from the salon's setting
 *   {{window_am}}   the same phrase in Amharic
 *   {{policy}}      the salon's own one-sentence rule
 *
 * A salon that changes the change window from 24 hours to 48 gets the terms to
 * say so, without touching the text; and nobody can type a window into the terms
 * that the booking service would refuse. Resolution is a single `strtr` pass, so
 * a placeholder cannot be substituted twice or read out of a customer's text.
 */
final class TermsService
{
    /** Tokens the editor may use, and what each one becomes. */
    public const TOKENS = ['{{window}}', '{{window_am}}', '{{policy}}'];

    public const UPDATED_SETTING = 'terms.updated_on';

    public function __construct(
        private readonly TermsRepository $terms,
        private readonly SettingsRepository $settings,
        private readonly ChangePolicy $policy,
    ) {
    }

    /**
     * The whole document.
     *
     * @return array{
     *     updated_on: string,
     *     sections: list<array{
     *         id: string,
     *         title: array{en: string, am: string},
     *         clauses: list<array{en: string, am: string}>
     *     }>
     * }
     */
    public function document(): array
    {
        $clauses = [];

        foreach ($this->terms->clauses() as $clause) {
            $clauses[(int) $clause['section_id']][] = [
                'en' => $this->resolve((string) $clause['body_en']),
                'am' => $this->resolve((string) $clause['body_am']),
            ];
        }

        $sections = [];

        foreach ($this->terms->sections() as $section) {
            $id = (int) $section['id'];

            $sections[] = [
                'id' => (string) $section['slug'],
                'title' => [
                    'en' => (string) $section['title_en'],
                    'am' => (string) $section['title_am'],
                ],
                'clauses' => $clauses[$id] ?? [],
            ];
        }

        return [
            'updated_on' => $this->updatedOn(),
            'sections' => $sections,
        ];
    }

    /**
     * Has the salon published a document yet?
     *
     * An installation that has never saved a section has nothing to serve, and
     * the website must then keep its own bundled copy rather than render an
     * empty legal page.
     */
    public function isPublished(): bool
    {
        return $this->terms->sectionCount() > 0;
    }

    /** The date under the text, as the panel last saved it. */
    public function updatedOn(): string
    {
        // SettingsRepository::get() returns '' when the key was never written,
        // so an unset date and a malformed one are the same case.
        $stored = $this->settings->get(self::UPDATED_SETTING, '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored) === 1) {
            return $stored;
        }

        return date('Y-m-d');
    }

    /**
     * What each placeholder becomes right now — shown in the editor so staff can
     * see the sentence they are building.
     *
     * @return array<string, string>
     */
    public function tokenValues(): array
    {
        return [
            '{{window}}' => $this->policy->windowLabel(),
            '{{window_am}}' => $this->policy->windowLabelAm(),
            '{{policy}}' => $this->policy->summary(),
        ];
    }

    /** Fill in the three placeholders in one pass. */
    public function resolve(string $text): string
    {
        return strtr($text, $this->tokenValues());
    }

    /**
     * Save the document's "last updated" date.
     *
     * Called on every edit rather than on a publish button: the terms are legal
     * text with no draft state, and a customer reading them should be able to
     * trust the date.
     */
    public function touch(): void
    {
        $this->settings->put(self::UPDATED_SETTING, date('Y-m-d'));
    }
}
