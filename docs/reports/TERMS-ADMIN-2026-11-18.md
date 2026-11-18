# Booking terms: editable in the panel

**Date:** 2026-11-18 · **Scope:** the admin panel, `GET /api/v1/terms`, and the
public terms page · **Branch:** `main`

## What was asked

> then in the admin panel add a future to edit and manage the entire terms in
> both language

The document at `/booking/information` shipped inside the site bundle, so a
wording change meant a code change and a deploy. This work moves it into the
panel: every section and every clause, in English and Amharic, editable by
someone who does not write code.

The first half of the same request — pushing the previous update — needed no new
commit: it was already on `origin/main` as `594e3dc`. See § Push.

## What changed

| Piece | File | Why |
| --- | --- | --- |
| Schema + seed | `admin/database/migrations/20261118000023_create_terms.php` | Two tables (`terms_sections`, `terms_clauses`), and the 12 sections / 30 clauses the site shipped with, so the editor opens on the document customers already read |
| Repository | `admin/src/Repositories/TermsRepository.php` | Sections and clauses, reordering, and moving a clause *inside its own section* |
| Service | `admin/src/Services/TermsService.php` | Assembles the document and fills `{{window}}` / `{{window_am}}` / `{{policy}}` from the salon's own booking rule |
| Policy phrases | `admin/src/Support/ChangePolicy.php` | `windowLabel()` / `windowLabelAm()` — the sentence fragments the placeholders become |
| Screen | `admin/src/Controllers/Admin/TermsController.php`, `admin/src/Views/admin/terms/index.php`, `admin/public/assets/admin.css` | One page: every section with its clauses under it, both languages side by side, rename / add / delete / ↑↓ |
| Routes | `admin/routes/admin.php`, `admin/routes/api.php` | Nine panel routes behind `auth` + `admin`; one public read |
| API | `admin/src/Controllers/Api/ContentApiController.php` | `GET /api/v1/terms`, with `published: false` for an installation that never saved a section |
| Site | `src/lib/terms-api.ts`, `src/routes/booking_.information.tsx` | Fetches the document; keeps the bundled copy as the fallback |

### The three placeholders

The terms are legal text about a rule the booking form enforces. If the salon
moves its change window from 24 hours to 48, the page must say 48 — without
anyone editing prose.

- `{{window}}` → “24 hours before the appointment” (or “any time before the appointment starts”)
- `{{window_am}}` → the same phrase in Amharic
- `{{policy}}` → the salon's one-sentence rule, word for word as the booking page prints it

They are resolved server-side, in one `strtr()` pass, when the document is
served. The editor shows what each one is currently worth, so staff can see the
sentence they are building. A placeholder can therefore never reach a customer,
and the text cannot promise a deadline the booking form would refuse.

### Decisions worth knowing

- **Both languages or neither.** A clause with English and no Amharic is refused
  by the server (and the same for a heading). Half a clause is a gap for the
  readers of the other language — worse than not shipping it.
- **Anchors are made once.** `slug` is generated from the English heading at
  creation and never edited, so `#window` survives a rewording and a contents
  link in an old email still lands.
- **Empty means “not taken over”.** An installation with no sections answers
  `published: false`; the site then keeps its bundled copy. A failed or slow
  request behaves the same way. Nobody ever sees a blank legal page.
- **No publish step.** A save is live, and stamps `terms.updated_on` — the date
  printed under the document. There is no draft state because there is no
  reviewer between the salon and its customers.

## Verification

Every command below was run in this workspace, against MariaDB 11.8 and PHP
8.4.27, on the commit that carries this work.

| Gate | Result |
| --- | --- |
| `php -l` on every touched PHP file | clean |
| `php vendor/bin/phpunit` | **228 tests, 25,403 assertions** — OK |
| `php /tmp/phpstan.phar analyse` (level 6) | `[OK] No errors` |
| `npx eslint .` | 0 problems |
| `npx tsc --noEmit` | 0 errors |
| `npx vitest run` | **114 tests** in 12 files |
| `npm run build` | built; 12 sections prerendered into `dist`/`.output` |
| `npx prettier --check` on touched files | clean |

### The seed is the page customers read today

The migration's rows were diffed against the bundled array in
`src/routes/booking_.information.tsx`: 12 sections, 30 clauses, identical
word for word, with the live sentence reduced to its placeholder. Only the
`window` clause differs, and only where the placeholder sits.

### Browser pass (Playwright, headless Chromium)

Panel, at 1280 px and 390 px, signed in as an administrator:

| Check | Result |
| --- | --- |
| The screen renders the whole document | 12 sections, 30 clause cards |
| Edit both languages and save | success message; `GET /api/v1/terms` serves the new text |
| The placeholder comes back filled in | “… up to 24 hours before the appointment …”, no `{{` |
| The date under the document moves | `updated_on` = the day of the save |
| Restore the original wording | back, both languages |
| A clause in one language only | refused, nothing stored (30 → 30), reason printed on the screen |
| Horizontal overflow | 0 px at both widths |
| Rename a section | heading changes, `#window` does not |
| Move a section / a clause | order changes; a clause never crosses into another section |
| Delete a section | it and its clauses leave; the rest stays numbered |
| No console errors | none |

Public page, served the way the host does it (built site + PHP behind the same
origin):

| Check | Result |
| --- | --- |
| The page renders the panel's text | a sentence typed straight into the database appeared as clause 3.1 — no deploy, no rebuild |
| Layout | 12 sections, 0 px overflow at 1280 px and 390 px, no console errors |
| With PHP answering `503` | still 12 sections / 30 clauses from the bundled copy, no console errors |

## Push

The previous request's work was already published: `git fetch origin main`
returned `594e3dc`, whose tree carried the money-free terms, the CI repair and
the documentation. No duplicate commit was made, and nothing was rewritten.

## Follow-ups that were not done

- The panel has no Content-Security-Policy yet (`docs/ROADMAP.md` item 4). The
  terms screen adds one small inline script for textarea sizing and no other
  inline code; when the CSP lands, that script needs a nonce or a file.
- Amharic in the panel relies on a system Ethiopic face. The panel now names
  `Noto Sans Ethiopic` / `Abyssinica SIL` in its stack, but it does not ship a
  webfont the way the public site does. A machine with no Ethiopic font will
  still show boxes — worth deciding on, not worth blocking this on.
