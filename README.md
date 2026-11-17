# WIZ Beauty Salon

The salon's website, its booking system, and the panel the salon runs it from — one repository, two runtimes.

| Part | What it is | Lives at | Source |
| --- | --- | --- | --- |
| **Public site** | Bilingual (English / አማርኛ) marketing site with service catalog, gallery and a guided booking flow. Prerendered to static HTML — no Node process in production. | `https://wiz.arkeonethiopia.com` | `src/` |
| **Panel** | Staff-facing admin: bookings, calendar, services, gallery, hours, messages, site content, email design, team, activity log. | `/admin` | `admin/` |
| **API** | The JSON the site reads and writes: catalog, gallery, reviews, opening hours, availability, bookings, messages. | `/api/v1/*` | `admin/src/Controllers/Api/` |

The panel and the API are the same PHP application. The site never holds a second copy of the salon's data: prices, hours, photos, the change window and the wording of the emails are all read from the database at request time.

---

## Documentation

Start here, then follow the link that matches what you are doing.

| If you want to… | Read |
| --- | --- |
| Understand how the pieces fit | [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) |
| Get it running on your machine | [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) |
| Run the tests, or add one | [docs/TESTING.md](docs/TESTING.md) |
| Know the booking rules and where each is set | [docs/BOOKING-RULES.md](docs/BOOKING-RULES.md) |
| Work on the API | [docs/API.md](docs/API.md) |
| Understand the database | [docs/DATA-MODEL.md](docs/DATA-MODEL.md) |
| Teach the salon staff to use the panel | [docs/ADMIN-HANDBOOK.md](docs/ADMIN-HANDBOOK.md) |
| Deploy, back up, monitor, roll back | [docs/OPERATIONS.md](docs/OPERATIONS.md) |
| Fix something that is broken right now | [docs/RUNBOOK.md](docs/RUNBOOK.md) |
| Take over the project | [docs/HANDOFF.md](docs/HANDOFF.md) |
| Contribute code | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Report a security problem | [SECURITY.md](SECURITY.md) |

Full index: [docs/README.md](docs/README.md).

---

## What the system does

**A customer** picks one or more services, sees a calendar in the Ethiopian calendar with the Gregorian date beside it, requests a time, and receives a confirmation email with a private link. That link lets them move or cancel the appointment themselves — up to the change window the salon sets (24 hours by default, any value from "no cut-off" to two weeks). Inside the window the page says so plainly and stops offering buttons the server would refuse. Before requesting, they can read the full booking terms in English and in Amharic at `/booking/information`.

**The salon** sees the request in the panel, confirms or declines it by phone, and the customer gets told. Staff never retype a booking; they manage one. Every status change, price edit and login is in the audit trail.

**Everything a customer reads** — opening hours, closures, phone number, WhatsApp, prices, service names and their Amharic labels, the change window, the terms' window clause, the email wording — is editable in the panel without a deploy.

---

## Quick start

Requirements: **PHP 8.4** with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `sodium` and `dom`, plus **MariaDB 11+**, for the panel; **Node 22.12+** with npm for the site (`nvm use` reads `.nvmrc`).

```bash
# 1. the panel and the API
cd admin
cp .env.example .env            # fill DB_*, APP_KEY; see docs/DEVELOPMENT.md
chmod 600 .env
composer install                # only needed for PHPStan and PHPUnit
php database/migrate.php migrate
php database/seed.php "Your Name" you@example.com "a-strong-password"
php database/seed_catalogue.php # optional: the 14 services the site already lists
php database/seed_media.php     # optional: the site's packaged pictures, into the panel
php -S 0.0.0.0:8080 -t public public/index.php
# → http://127.0.0.1:8080/admin

# 2. the site (second terminal, repository root)
npm install
npm run dev                     # → http://127.0.0.1:5173
```

`vite.config.ts` proxies `/api` and `/uploads` to `http://127.0.0.1:8000` in development, because the site and the panel run on different ports there. Point the PHP server at `:8000` — or change the proxy — so the booking flow can reach the API.

Full setup notes, including the `APP_KEY` and database user: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

---

## Commands

Run from the repository root:

```bash
npm install            # install from the committed package-lock.json
npm run dev            # dev server with HMR
npm run build          # production build → .output/public
npm run preview        # serve the build over HTTP, as production would
npm run lint           # ESLint (reports Prettier drift too)
npm run format         # apply Prettier
npm run test           # Vitest
npm run typecheck      # tsc --noEmit
```

Inside `admin/`:

```bash
composer lint          # php -l over src, config, database, tests, public
composer analyse       # PHPStan
composer unit          # PHPUnit
composer quality       # lint + analyse + unit + composer audit
php database/migrate.php migrate|status|rollback
php database/seed.php "Name" you@example.com "password" [--force]
php bin/housekeeping.php
php bin/preflight.php   # pre-deploy checks (see docs/OPERATIONS.md)
```

CI runs all of the above on every push to `main`, plus the migrations against a live MariaDB and a `composer audit` — see [.github/workflows/ci.yml](.github/workflows/ci.yml). Nothing is deployed unless the whole suite passes.

---

## Repository map

```
.
├── src/                  the public site (TanStack Start, React 19, Tailwind 4)
│   ├── routes/           one file per page, file-based routing
│   ├── components/site/  SiteChrome (header, footer, hero), SmartImage
│   ├── components/ui/    shadcn/ui primitives
│   ├── lib/              API clients, Ethiopian calendar, reveal, error capture
│   ├── data/             navigation, editorial copy, service catalog fallback
│   └── styles.css        the design system and every page's styles
├── public/               static files copied into the build
├── shared/               calendar test vectors used by both runtimes
├── scripts/              low-bandwidth and build-preview tooling
├── admin/                the PHP panel and the JSON API  (see admin/README.md)
│   ├── src/Core/         router, container, request, response, session, CSRF
│   ├── src/Controllers/  one class per screen, plus Api/
│   ├── src/Services/     rules that span more than one repository
│   ├── src/Repositories/ every SQL statement in the project
│   ├── src/Support/      EthiopianDate, SignedLink, ChangePolicy, helpers
│   ├── src/Views/        plain PHP templates
│   ├── database/         migrations, seeds
│   ├── deploy/           server-side deploy script and docroot shims
│   └── tests/            PHPUnit
├── docs/                 architecture, operations, handoff, ADRs, reports
└── .github/workflows/    CI and the deploy-branch publisher
```

---

## Status

- **Live and in use:** the site, the panel and the booking system, including multi-service bookings, the configurable change window, and the bilingual terms page.
- **Placeholders remaining:** real photography for the catalog, real prices where the salon has not published them, and customer testimonials until the salon supplies them. Every placeholder is labelled in the UI rather than looking like a finished claim.
- **Known gaps** (also in [docs/RUNBOOK.md](docs/RUNBOOK.md) and [SECURITY.md](SECURITY.md)): no Content-Security-Policy on the panel's HTML, no automated off-site backup, no uptime monitoring or alerting. These are operational decisions for the salon, not code defects — none of them blocks the current deployment, and each has a written workaround.

---

## Conventions

- **Nothing is served from `main`.** `main` is source; CI builds and publishes an assembled `deploy` branch, and the server pulls that. See [docs/OPERATIONS.md](docs/OPERATIONS.md).
- **Small, single-purpose commits** with conventional prefixes (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`) and a body that says what was wrong, what changed and how it was verified.
- **No secrets in the repository.** `.env` files, keys and tokens stay out; `SECURITY.md` says what to do if one leaks.
- **Published history is not rewritten.** No force-push, rebase or amend on `main`.

---

## Ownership

Software built for **WIZ Beauty Salon**, Addis Ababa. Proprietary — see [LICENSE](LICENSE). Security reports: see [SECURITY.md](SECURITY.md).
