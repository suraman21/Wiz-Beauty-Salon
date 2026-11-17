# Development

Two runtimes to install, two servers to run. Neither needs Docker; both work on macOS, Linux and Windows (WSL recommended on Windows, because of the shell scripts).

---

## What you need

| | Version | Why |
| --- | --- | --- |
| **PHP** | 8.4 (`^8.2` works) | panel and API |
| **Extensions** | `pdo`, `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `sodium`, `json`, `dom` | declared in `admin/composer.json`; `dom` arrives with HTML Purifier's requirements |
| **MariaDB** | 11.x (MySQL 8 works) | the salon's data |
| **Node** | **22.12 or newer**, with npm | the site's toolchain. `.nvmrc` pins 22 |
| **Composer** | 2.x | *only* for PHPStan and PHPUnit; the app boots without it |

`nvm use` in the repository root picks up `.nvmrc`. `node -v` must print `v22.…` — older Node still builds but the TanStack Start packages declare 22.12 as their floor and npm will warn on every install.

---

## First run: the panel

```bash
cd admin
cp .env.example .env
chmod 600 .env
```

Open `.env` and set, at minimum:

```ini
APP_URL=http://127.0.0.1:8000
APP_ENV=local
APP_DEBUG=true
APP_KEY=            # generate below

DB_HOST=127.0.0.1
DB_NAME=wiz
DB_USER=wiz
DB_PASS=wiz

UPLOAD_PATH=/absolute/path/to/Wiz-Seauty-Salon/admin/storage/uploads
UPLOAD_URL=/uploads
```

Generate the key — it signs the customer links, so it must be 64 hex characters and it must be stable:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Create the database and run the migrations:

```bash
mysql -u root -e "CREATE DATABASE wiz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php database/migrate.php migrate      # 22 migrations
php database/seed.php "Your Name" you@example.com "a-strong-password"
php database/seed_catalogue.php       # optional: the 14 services from database/seeds/catalogue.json
php database/seed_media.php           # optional: the site's packaged pictures, so the panel is not empty
composer install                      # optional: PHPStan + PHPUnit only
```

Serve it:

```bash
php -S 0.0.0.0:8000 -t public public/index.php
# panel:    http://127.0.0.1:8000/admin/login
# API:      http://127.0.0.1:8000/api/v1/services
```

`0.0.0.0`, not `127.0.0.1`: the panel fires emails and absolute links from `APP_URL`, and a host that only listens on loopback is invisible from a phone on the same network.

---

## First run: the site

In a second terminal, at the repository root:

```bash
npm install          # package-lock.json is committed; npm ci also works
npm run dev          # http://127.0.0.1:5173
```

The dev server proxies `/api` and `/uploads` to **`http://127.0.0.1:8000`** (`vite.config.ts`). If your PHP server is on another port, change the proxy rather than the API URLs — the site always calls same-origin paths so that production needs no CORS.

Booking requires both servers: without the PHP side the wizard renders from the bundled fallback catalogue and fails politely when it tries to create a booking ("The salon's booking service is not reachable right now").

---

## Day-to-day commands

Repository root | What it does
--- | ---
`npm run dev` | Vite dev server, HMR, `/api` proxied
`npm run build` | production build → `.output/public`
`npm run preview` (or `preview:static`) | serve the build over HTTP exactly as production would
`npm run lint` | ESLint (also flags Prettier drift)
`npm run format` | Prettier, in place
`npm run test` | Vitest
`npm run typecheck` | `tsc --noEmit`

`admin/` | What it does
--- | ---
`composer lint` | `php -l` on every PHP file (src, config, database, tests, public, bin)
`composer analyse` | PHPStan, level 6
`composer unit` | PHPUnit
`composer quality` | lint + analyse + unit + audit — the CI gate
`php database/migrate.php status` | which migrations are applied
`php database/migrate.php rollback` | undo the last one
`php bin/preflight.php` | deployment readiness (see `docs/OPERATIONS.md`)
`php bin/housekeeping.php` | prune expired sessions, rate limits, old logs

**Tests wipe their database.** PHPUnit runs against whatever `DB_NAME` points at and truncates tables between tests. Give the tests their own database so a test run cannot empty your working copy:

```bash
mysql -u root -e "CREATE DATABASE wiz_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_NAME=wiz_test php vendor/bin/phpunit
```

CI always runs this way (`wiz_test`, see `.github/workflows/ci.yml`).

---

## How the code is organised

- **No SQL outside `src/Repositories/`.** If you find yourself writing a query in a controller, add a repository method instead.
- **Controllers own requests, services own rules, repositories own rows.** `src/Core/` knows nothing about salons.
- **Views escape on output** with `e()`. Raw output is allowed only where a comment says why.
- **Every `POST` is CSRF-checked** by the middleware chain, not per route.
- **Configuration comes from `Env`**, defaults live in `admin/config/*.php`, and nothing reads `$_ENV` directly.
- **Site styles live in `src/styles.css`.** There are no CSS modules and no per-page stylesheets; each page's block is marked with a comment naming the page. Keep new rules inside the block they belong to.
- **The typed API surface is in `src/lib/*-api.ts`.** A new endpoint gets a function there and a test in `src/test/`.

Longer version: `docs/ARCHITECTURE.md`.

---

## Adding a feature, end to end

A worked example — "the salon wants to hide a service from the booking page":

1. **Database.** Add a migration (`admin/database/migrations/YYYYMMDDHHMMSS_description.php`) with a reversible `up()`/`down()`. Never edit an applied migration.
2. **Repository.** Add the column to the select lists in `ServiceCategoryRepository`.
3. **Screen.** Add the checkbox to `src/Views/admin/categories/`, handle it in `CategoryController`, and check CSRF/`AdminOnly` where it belongs.
4. **API.** Decide whether the field is public. If yes, add it in `Api/BookingApiController::services()` — additive only; never change an existing field's shape.
5. **Site.** Consume it in the page that needs it, with the fallback path still working when the API is old or down.
6. **Tests.** A PHPUnit feature test for the screen/API behaviour, a Vitest test for the client logic.
7. **Docs.** If it changes a rule or a setting the salon can see, update `docs/BOOKING-RULES.md` or `docs/ADMIN-HANDBOOK.md` in the same commit.

---

## Gotchas

- **`APP_KEY` is not a toy.** Changing it invalidates every manage link already sitting in a customer's inbox. Generate once per environment.
- **The session timezone comes from `APP_TIMEZONE`.** Without it, `NOW()` disagrees with the salon's clock and "today" filters drift by hours (`admin/config/database.php` explains this).
- **Uploads live outside the docroot.** `UPLOAD_PATH` must be writable and must **not** be inside `public/`, or uploaded files become executable.
- **`admin/public/.htaccess` denies dotfiles**, and production serves the app through a shim; don't "simplify" it into the docroot.
- **Vitest runs in jsdom.** Browser-only APIs need a guard or a stub; the tests for `SmartImage` show the pattern.
- **`src/routeTree.gen.ts` is generated.** Do not hand-edit it; it is rewritten by the dev server and the build.
- **Emails do not send in development** unless `MAIL_*` is configured. The panel's Settings → Email has a "send a test" button, and the log records what would have been sent.
