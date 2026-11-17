# Architecture

Two applications, one repository, one database. This document explains what each part is responsible for, how they talk, and why the split is where it is.

---

## The shape of the system

```
                     ┌──────────────────────────────────────────────┐
   customer  ──────► │  Public site  (static HTML + JS)             │
                     │  wiz.arkeonethiopia.com                      │
                     └───────────────┬──────────────────────────────┘
                                     │ fetch() to /api/v1/*
                                     ▼
                     ┌──────────────────────────────────────────────┐
   staff     ──────► │  PHP 8.4 application                         │
                     │  /admin/*  (screens)   /api/v1/*  (JSON)     │
                     └───────────────┬──────────────────────────────┘
                                     │ PDO, prepared statements
                                     ▼
                     ┌──────────────────────────────────────────────┐
                     │  MariaDB 11   ── uploads/ on disk            │
                     └──────────────────────────────────────────────┘
```

There is no third service, no queue, no cache server, no CDN dependency. The site is files; the application is PHP; the state is MariaDB plus the uploads directory. That is the entire production footprint, and it is deliberate: the salon's hosting is a single cPanel account with PHP and MySQL, and anything that needs a daemon to stay alive would be a support problem the salon cannot absorb.

---

## The public site (`src/`)

**TanStack Start + TanStack Router**, React 19, TypeScript, Tailwind 4, built by **Vite 8**. Routes are file-based under `src/routes/`; each exported route renders to HTML at build time and hydrates in the browser.

| Route | File | Notes |
| --- | --- | --- |
| `/` | `routes/index.tsx` | hero, services, gallery, testimonials, CTA |
| `/services` | `routes/services.tsx` | catalog by category |
| `/gallery` | `routes/gallery.tsx` | filterable gallery |
| `/about` | `routes/about.tsx` | story, team, placeholders where content is missing |
| `/contact` | `routes/contact.tsx` | prints contact details and hours from the API |
| `/booking` | `routes/booking.tsx` | the guided booking flow |
| `/booking/information` | `routes/booking_.information.tsx` | bilingual terms, EN / አማርኛ |
| `/booking/recover` | `routes/booking_.recover.tsx` | "I lost my link" |

The customer's own page — `/booking/manage/{token}` — is **not** part of the static site. PHP renders it (`PublicBookingController`, `src/Views/public/`), because it shows one customer's appointment and must never be cached or prerendered. It is served from the same origin and carries the same header and footer, so it reads as part of the site without being part of its build.

Everything the site shows that the salon can change comes from the API at runtime, not from the bundle: services, photos, reviews, contact details, hours, closures, the change window. The bundle carries a **fallback** copy (`src/data/services.ts`) so the page still renders if the API is down — the fallback is visibly a fallback (catalogue placeholders say so) rather than a silent lie.

The site holds **no secrets**. It has no server-side component in production, so there is nothing to leak: tokens and database credentials live only in the PHP application.

### Why prerendered static files

- The salon's host serves static files without a Node runtime, and a PHP-only host cannot run an SSR process.
- Ethiopian mobile networks make the first byte matter more than the bundle size; a static file from a local host is faster than any PHP render.
- A static site cannot be brought down by an unhandled exception. The API can be, and then the pages still load with their fallback content.

### Client-side helpers worth knowing

- `src/lib/api.ts` + `booking-api.ts`, `content-api.ts`, `salon-api.ts`, `contact-api.ts` — every call to `/api/v1/*`, one module per resource, with the API base and the error shape in one place.
- `src/lib/ethiopian-calendar.ts` — Ethiopian date and clock arithmetic (see `docs/adr/0001-ethiopian-calendar-and-clock.md`); the same arithmetic exists in `admin/src/Support/EthiopianDate.php`, and `shared/` holds the test vectors that keep the two honest.
- `src/components/site/SmartImage.tsx` — retrying image loader: three retries with growing waits, each against a cache-busted URL, and a drawn stand-in instead of a broken glyph if all of them fail. Every image on the site goes through it.
- `src/lib/error-capture.ts` — catches unhandled errors and reports them to the panel's log, so a customer's broken page leaves a trace.

---

## The PHP application (`admin/`)

Plain PHP 8.4 with no framework, no ORM and no template engine. Composer is used for **development tooling and two libraries** (PHPMailer for SMTP, HTML Purifier for the email designer's output); the foundation runs on hand-written code so that a copy of the folder works on a host where `composer install` was never run.

The layering is strict, and PHPStan enforces most of it:

| Layer | Directory | Rule |
| --- | --- | --- |
| Core | `src/Core/` | HTTP, session, CSRF, config, database, router, view, validation. No knowledge of salons or bookings. |
| Middleware | `src/Core/Middleware/` | `Auth`, `Guest`, `AdminOnly`, `Csrf` — one decision each. |
| Controllers | `src/Controllers/` | Translate a request into calls on services and a response. No SQL. |
| Services | `src/Services/` | Rules that span repositories: booking creation, availability, cancellation policy in force, email composition. |
| Repositories | `src/Repositories/` | **Every SQL statement in the project.** Nothing else may write SQL. |
| Support | `src/Support/` | Pure helpers: `EthiopianDate`, `SignedLink`, `ChangePolicy`, `SalonDetails`, `Slug`, `Paginator`, `Origin`. |
| Domain | `src/Domain/` | Value objects and enums with no I/O. |
| Views | `src/Views/` | PHP templates that escape on output. |

Two API controllers sit beside the screen controllers in `src/Controllers/Api/`: `ContentApiController` (read-only content), `BookingApiController` (availability, bookings, recovery) and `MessageApiController` (contact form). They exist separately because they answer to the browser, not to a logged-in staff member.

### The request lifecycle

1. `public/index.php` builds the container, loads `.env`, opens the session and hands a `Request` to the `Kernel`.
2. `Kernel` matches the path against `routes/admin.php` or `routes/api.php`, runs the route's middleware chain, and calls the controller.
3. The controller calls a service or repository, then returns a `Response` — HTML from a rendered view, or JSON.
4. `Kernel::harden()` adds the security headers to every response before it is sent.

Errors are `HttpException` with a status code; anything else is logged with a request id and rendered as the error page, never as a stack trace on a production host.

### Security decisions in the foundation

| Decision | Where | Why |
| --- | --- | --- |
| Every state-changing form carries a CSRF token | `Core/Csrf`, `Middleware/Csrf` | The panel is a cookie-authenticated app on shared hosting. |
| Sessions are regenerated on login and rotated on privilege change | `Core/Auth`, `Core/Session` | Session fixation. |
| Login is rate-limited per email **and** per IP | `Core/Auth` + `rate_limits` | Credential stuffing against a public `/admin`. |
| Passwords are `password_hash()` with the default algorithm | `Core/Auth` | Rehash-on-login is handled. |
| Uploads are validated by content, re-encoded through GD, and stored outside the docroot | `Services/Uploader` | An uploaded "image" must never be executable, and must never sit where PHP runs. |
| The email designer's HTML is sanitised by HTML Purifier, then sent | `Services/EmailTemplateService` | Staff-authored HTML goes out under the salon's name. |
| Security headers on every response; HSTS when HTTPS | `Kernel::harden()` | Defence in depth, cheap to add. |
| API writes are origin-checked and rate-limited | `Support/Origin`, `rate_limits` | The API is public; the form is not the only thing that can post to it. |
| Logs and audit trail are append-only by convention and pruned by `bin/housekeeping.php` | `activity_log`, `storage/logs` | The salon needs a record of who changed what. |

Known gaps — no CSP on panel HTML, no automated off-site backup, no uptime monitoring — are listed in `SECURITY.md` and `docs/RUNBOOK.md` with their workarounds.

---

## How the two halves talk

The site calls the API over HTTPS with `fetch`, same origin in production (`https://wiz.arkeonethiopia.com/api/v1/...`), proxied in development (`vite.config.ts` → `127.0.0.1:8000`).

- **Reads** are cached for 60 s (content) to 300 s (gallery, reviews) by `Cache-Control`, so a busy page does not hit the database per view and a staff correction is visible within a minute.
- **Writes** (`POST /api/v1/bookings`, `/bookings/recover`, `/messages`) are origin-checked, rate-limited, validated server-side regardless of client validation, and answered with the created reference.
- **The change window** is read from the database on both sides. The site asks `/api/v1/salon` for `policy` and prints the sentence the panel composes — one source of truth, so the promise on the page and the rule the server enforces cannot drift.

Nothing else is shared: there is no shared session, no shared token, no second copy of the catalogue in the bundle beyond the labelled fallback.

---

## Deploy topology

`main` is source. CI builds the site, assembles a `deploy` branch containing **both** the built static site and the PHP application, and the server pulls that branch. The docroot layout, the cron pull and the rollback are in `docs/OPERATIONS.md`. The short version:

```
main ──CI──► deploy branch ──cron pull──► ~/wiz.arkeonethiopia.com/
                                            ├── public_html/   ← site files (docroot)
                                            ├── admin/         ← PHP application (outside docroot)
                                            ├── uploads/       ← staff uploads (written by PHP)
                                            └── shared/.env    ← secrets, never in git
```

---

## Where the design decisions are recorded

- `docs/adr/` — accepted decisions with their alternatives (Ethiopian calendar and clock).
- `docs/PRE-PRODUCTION-AUDIT.md` — the audit that preceded go-live: what was checked, what was found, what was fixed.
- `docs/BOOKING-RULES.md` — every booking rule and the exact place it is set.
- `docs/DECISIONS.md` — the short log of choices made during this build that are not ADR-sized.
