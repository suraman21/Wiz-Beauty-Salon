# Changelog

What changed, newest first. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); the project does not use semantic version numbers, so sections are dated and tied to the `main` commit that carries them.

The project has been live since 2026-10-23. Everything before that date is the build.

---

## Unreleased

### Fixed
- **The security job scanned nothing and called it clean.** Its checkout was a one-commit clone, so the commit range gitleaks derived from the push event could not be resolved; it read zero bytes and printed "no leaks found". The job now fetches full history and runs gitleaks directly over every ref, and the marketplace action is gone. Verified locally with the same build: 131 commits, 11.2 MB, no leaks.
- Dependabot opened twelve pull requests in its first minute, eleven of them major-version bumps (jsdom 20 → 31, PHPUnit 11 → 13, PHPMailer 6 → 7), all red. Majors are now ignored on npm and composer and the open-PR limit is three; the twelve were closed. A wall of red bot pull requests teaches everyone to ignore the colour.

### Added
- `bin/preflight.php` — one command that checks a server is ready to serve: PHP version and extensions, `.env` readability, `APP_KEY`, upload and storage paths, database connection, migrations applied, an active administrator, and whether the tree looks like a release.
- `scripts/serve-build.mjs` (`npm run preview`) — serves `.output/public` the way the host will, because `vite preview` looks for an SSR entry this build does not produce.
- This documentation set: architecture, development, testing, API, data model, booking rules, operations, hosting, runbook, handoff, decisions, a staff handbook, and an index.
- `CONTRIBUTING.md`, `SECURITY.md`, `.editorconfig`, issue and pull-request templates.

### Changed
- **The booking terms no longer mention money.** The salon publishes no prices and takes payment in person, so the "Prices and payment" section (3 clauses), the "Deposits for long or bridal bookings" section (3 clauses), the fee sentence in "Arriving late", the deposit consequence in "If you do not come", the "nothing is owed" clause in "Changing or cancelling" and the refund remedy in "If something is not right" are gone. The document is now 12 sections and 30 clauses, and both languages moved together — a reader is never shown an English paragraph whose Amharic counterpart was deleted.
- **Package manager: Bun → npm.** `package-lock.json` is now committed and CI runs `npm ci` on Node 22. Two lockfiles drift; `npm ci` is reproducible and ships with Node. Bun's 24-hour supply-age guard is replaced by Dependabot plus the existing audit job.
- `package.json` renamed from the template's `tanstack_start_ts` to `wiz-beauty-salon`, pinned `engines.node >= 22.12.0`, and gained `typecheck` and `preview:static` scripts.
- `admin/composer.json`'s lint and PHPStan now cover `bin/`, so a deployment script cannot rot unnoticed.
- Documentation moved out of the repository root: `CPANEL-SETUP.md` → `docs/HOSTING-CPANEL.md`, `DEPLOY.md` → `docs/OPERATIONS.md`, working reports → `docs/reports/`, `roadmap.md` → `docs/ROADMAP.md`.

### Removed
- `bun.lock`, `bunfig.toml`, and the two stale root documents that described a project this is no longer.

---

## 2026-11-17 — `f95153b`

### Fixed
- **A security scan that read nothing reported success.** The gitleaks job checked out shallowly, could not resolve its base commit, and exited 0 having scanned zero bytes. The job now fetches full history (`fetch-depth: 0`).
- **PHPStan's null.** `ChangePolicy::hours()` treated an unset setting as `null`; the settings repository never returns `null`, it returns the default it was given. One arm of the condition was dead code and the analyser was right to say so.

## 2026-11-17 — `25e7af0`

### Added
- **Booking terms in English and Amharic** at `/booking/information`: 14 sections, 38 clauses (now 12 and 30 — the money sections were removed on 2026-11-17), a language switch that keeps the reader's place, and a window clause generated from the salon's actual setting rather than typed. Linked from the wizard, the confirmation screen, the recovery page, the footer and the manage page.

## 2026-11-17 — `a48a484`

### Added
- **The change window is a setting.** `booking.change_window_hours` (default 24, up to 336, `0` meaning no cut-off) with presets in Settings → Booking rules. `Support/ChangePolicy` is now the only class that answers "how late may a customer change this?", and the customer page, the API, the emails and the terms all read their wording from it.

### Fixed
- The manage page no longer offers a cancel or reschedule button the server would refuse: inside the window it shows the reason and the salon's phone number instead.

## 2026-11-17 — `47e1c36`, `dd3b59d`

### Fixed
- **A picture that has loaded is a picture, not a stand-in.** A retry landing after a successful load could swap the real image back to the drawn placeholder.
- The hero survives a slow connection: the photograph, the text and the drawn fallback each have their own path, and text never waits for an image.

## 2026-11-17 — `0ad8bb9`

### Changed
- Four cuts of the hero: each public page opens on its own photograph instead of one repeated layout, and the mobile hero fits one screen.

## 2026-11-17 — `acd9e1d`, `9a97cec`

### Added
- Configurable gap between bookings, and several services in one visit, priced and timed as one appointment.

### Changed
- The clock is twelve-hour throughout, with full month names and `ጠዋት / ከሰዓት / ማታ` — no 24-hour numbers anywhere a customer reads.

## 2026-11-17 — `bf6b8bb`, `1afd713`

### Added
- **Every date and time reads as Ethiopian first**, with the Gregorian date in brackets. Site, API and panel share the arithmetic; `shared/` holds the vectors both suites test against.

### Fixed
- Ethiopian month lengths were being derived from Gregorian leap years, which put Pagume in the wrong year.

## 2026-11-16

### Added
- The home page rebuilt on the salon's own artwork: glass bar, staggered hero, one-screen mobile layout.
- Gallery: photographs chosen in the panel lead the home page's visual section.
- Contact: location, hours and a real map frame; the page reads the salon's details from the panel.

### Changed
- One flat catalogue of services, with no prices and no running times: what happens inside a service is agreed at the salon.

## 2026-10-23

### Added
- "I'm not sure yet" and multi-service bookings.
- **Recovery by phone or email** at `/booking/recover`, so a customer who lost their link can get it back without calling.
- A distinct manage page with the customer's own link, a copy button and a preview of what the salon will see.

### Fixed
- `/booking/recover` rendered the booking wizard instead of the recovery page (a nested route with no outlet).
- Phone lookup only scanned twenty rows and missed bookings once the salon was busy.

## 2026-10-20

### Added
- The panel's settings screen answers "is email working?" at the top of the page.
- Logo, scrollable category row and a gallery collage.

### Changed
- Every picture and every detail the panel owns is now the same one the website shows.

## 2026-10-19 — the build

### Added
- The plain-PHP panel: router, authentication, migrations, dashboard, services, categories, gallery, reviews.
- Bookings: hours, availability, approval, the public flow, the customer's own cancel and reschedule.
- Messages from the contact form, with the salon notified.
- Email: a design studio with revision history, a transport seam, and a "send a test" button.
- Team and settings: accounts, roles, email delivery.
- Trusted proxy handling, so client IPs survive Cloudflare.
- Tests across both stacks, and a CI pipeline that builds, checks, scans and publishes a `deploy` branch.

### Fixed
- The deploy script is merge-safe: it strips legacy `.htaccess` blocks, keeps server-owned directories out of `rsync --delete`, and health-checks the origin by IP rather than through Cloudflare.

## 2026-10-04 → 2026-10-18 — the site

### Added
- The first home page, and the pages built on it: services, gallery, about, contact, booking.
- Prerendering: every route becomes static HTML, so production needs no Node process.
