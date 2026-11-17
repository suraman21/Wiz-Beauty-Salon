# Testing

Two suites, one gate. Nothing merges to `main` unless both are green and the site builds.

| Suite | Runner | Count today | Command |
| --- | --- | --- | --- |
| Site | Vitest (jsdom) | **104 tests** | `npm run test` |
| Panel / API | PHPUnit 11 | **211 tests, 25,354 assertions** | `DB_NAME=wiz_test php vendor/bin/phpunit` |

Plus three checks that are not tests but catch the same class of mistake: ESLint (`npm run lint`), `tsc --noEmit` (`npm run typecheck`), and PHPStan level 6 (`composer analyse`).

---

## Running everything the way CI does

```bash
# site
npm ci
npm run lint
npm run typecheck
npm run test
npm run build

# panel (against a throwaway database)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wiz_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd admin
DB_HOST=127.0.0.1 DB_NAME=wiz_test DB_USER=root DB_PASS= \
  php vendor/bin/phpunit

# static analysis
php vendor/bin/phpstan analyse --no-progress     # or: composer analyse
```

`composer quality` runs lint + analyse + unit + `composer audit` in one go, and is what the CI admin job approximates. CI additionally **runs the migrations against a real MariaDB 11.4**, runs them twice (the second must be a no-op), seeds an administrator twice (the second without `--force` must be refused, with `--force` must reset), and runs `composer audit --locked`.

---

## What the tests are for

The suites are shaped by what has actually gone wrong, not by coverage targets.

### Site (Vitest, `src/test/`, `src/lib/*.test.ts`)

| Area | What it pins down |
| --- | --- |
| `ethiopian-calendar.test.ts` | Ethiopian ⇄ Gregorian conversion, month lengths, Pagume in a leap year, the clock's `ጠዋት/ከሰዓት/ማታ` and the absence of 24-hour numbers |
| `booking-api.test.ts` | request shapes, the error path, the fallback when the API is unreachable |
| `content-api.test.ts`, `salon-api.test.ts` | that a missing or partial payload degrades to the bundled content instead of throwing |
| `smart-image.test.tsx` | retries, cache-busting per attempt, the drawn stand-in after the last failure, and no broken image ever rendering |
| `app-routing.test.tsx` | that every route in the tree resolves |
| `recover-manage.test.ts` | the recover form and the manage page's rules, including the refusal inside the change window |
| `services-booking.test.ts`, `service-pages.test.ts` | the catalogue → wizard hand-off and the per-service deep links |
| `site-content.test.ts` | the editorial copy and the Amharic strings that must not be empty |

The calendar arithmetic is duplicated on purpose — TypeScript for the site, PHP for the panel — and `shared/` holds the test vectors both suites read. If you change one implementation, both suites fail until the other agrees.

### Panel (PHPUnit, `admin/tests/`)

| File | What it pins down |
| --- | --- |
| `Feature/BookingFlowTest.php` | create → confirm → cancel; double-booking rejected at the slot key; the change window enforced on cancel and reschedule |
| `Feature/PublicContentTest.php` | the API's shapes and cache headers; unpublished things stay unpublished |
| `Feature/ChangePolicyTest.php` | presets, free hours, the "no cut-off" case, and the sentence the customer reads |
| `Feature/RateLimitTest.php` | limits hold, windows expire, one address cannot lock out another |
| `Feature/BookingNotifierTest.php` | who gets an email for which transition, and what the link in it points at |
| `Feature/TeamTest.php` | role gates: staff cannot reach admin-only screens |
| `Feature/SiteMediaRepairTest.php` | a replaced picture leaves no orphan and breaks no slot |
| `Feature/CalendarBulkTest.php` | the month view agrees with the day view, day for day |
| `Feature/ContentSchemaTest.php` | the API's field types do not drift (the site's types are generated from the same shape) |
| `Feature/MessageInboxTest.php` | the contact form stores even when notifying the salon fails |
| `Feature/EmailTemplateServiceTest.php` | sanitising, publishing and rolling back an email design |
| `Feature/MailSettingsTest.php`, `Feature/ContainerBindingsTest.php` | settings round-trip; every class the container claims to build actually builds |
| `Unit/*` | `EthiopianDate`, `SlotCalculator`, `SignedLink`, `Slug`, `Paginator`, `Validator`, `Request` (trusted proxies), `Csrf`, `Config` defaults, `EditorAssets` — each alone |

### Not covered by tests

Be honest about the gaps rather than pretend:

- **Email delivery** is stubbed; SMTP is exercised by the panel's "send a test" button, not by CI.
- **cPanel deployment** is tested by running it. `admin/deploy/deploy.sh` has no test double.
- **Browser rendering** is checked by hand and by the Playwright pass recorded in `docs/PRE-PRODUCTION-AUDIT.md` (1280 / 768 / 390 px, zero horizontal overflow).

---

## Writing a test

- **Bug first.** If a test would not have failed before the fix, it is not testing the bug.
- **Database tests use the feature test base**, which truncates the tables it touches and never relies on another test's leftovers.
- **No sleeps.** The rate-limit tests travel in time by writing expiries, not by waiting.
- **Deterministic clock.** Anything date-sensitive takes "now" as a parameter; `AvailabilityService` and `EthiopianDate` both do.
- **One assertion of behaviour per test.** "Returns 422 and creates no row" is one behaviour; "returns 422, logs, and emails" is three tests.

---

## Continuous integration

`.github/workflows/ci.yml`, four jobs, all required before the `deploy` branch is republished:

1. **Site** — `npm ci`, lint, typecheck, test, build, artifact upload.
2. **Admin** — `composer validate --strict`, install, `php -l` everywhere, PHPStan, PHPUnit, migrations twice, seed twice, `composer audit`.
3. **Security** — `npm audit` (advisory, not a gate: a CVE in a build tool must not stop a release that ships no server-side Node) and **gitleaks** over the full history.
4. **Publish deploy branch** — assembles `site/` + `admin/` (with `vendor/`, without `tests/` or `.env`), writes `RELEASE` with the commit SHA, force-orphans the `deploy` branch.

The gitleaks job checks out with `fetch-depth: 0`; on a shallow clone it resolves a missing base commit and exits 0 having scanned nothing, which is the one failure mode that looks like success.
