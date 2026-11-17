# The panel and the API

Plain PHP 8.4 and MariaDB. No framework, no ORM, no template engine. This folder powers `/admin` and `/api/v1`, and renders `/booking/manage/{token}`.

For everything around this folder — the public site, deployment, the database as a whole — start at [../README.md](../README.md) and the [documentation index](../docs/README.md). This file is the short version for someone already inside `admin/`.

```
admin/
├── bin/              preflight.php (deployment check), housekeeping.php (pruning)
├── bootstrap/        autoloader + service container
├── config/           app, database, mail defaults — all read from .env
├── database/         migrations, migrate.php, seed*.php, seeds/*.json
├── deploy/           deploy.sh, docroot shim, .htaccess block
├── public/           front controller + compiled admin assets
├── routes/           admin.php (screens), api.php (JSON)
├── src/
│   ├── Controllers/  one class per screen, plus Api/ and the public booking page
│   ├── Core/         App, Router, Request, Response, View, Session, Csrf, Kernel, …
│   ├── Database/     the migration contract
│   ├── Domain/       enums and value objects, no I/O
│   ├── Repositories/ every SQL statement in the project
│   ├── Services/     rules that span more than one repository
│   ├── Support/      EthiopianDate, ChangePolicy, SignedLink, SalonDetails, …
│   └── Views/        plain PHP templates (admin, public, mail, layouts, errors)
├── storage/          logs, sessions, cache, uploads (never committed)
└── tests/            PHPUnit
```

## First run

```bash
cp .env.example .env
chmod 600 .env                # fill in DB_* and APP_KEY first
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # → APP_KEY

php database/migrate.php migrate
php database/seed.php "Your Name" you@example.com "a-strong-password"
php -S 0.0.0.0:8000 -t public public/index.php      # http://127.0.0.1:8000/admin
```

`composer install` is optional: `bootstrap/autoload.php` falls back to a PSR-4 loader, so
the panel boots without Composer. Composer is needed only for PHPStan and PHPUnit — and
`vendor/` must exist on the server for PHPMailer and HTML Purifier, which the release
ships.

## Checks

```bash
composer lint      # php -l on src, config, database, tests, public, bin
composer analyse   # PHPStan, level 6
composer unit      # PHPUnit  (point DB_NAME at a throwaway database — tests truncate)
composer quality   # all of the above, plus composer audit
php bin/preflight.php    # is this server ready to serve?
```

## Screens

| Route | Screen | Who |
| --- | --- | --- |
| `/admin/bookings` | Bookings, new booking, one booking (status, reschedule, notes, history) | all staff |
| `/admin/categories` | **Services** — what the salon offers | all staff |
| `/admin/gallery` | Gallery photos | all staff |
| `/admin/images` | Website image slots (hero, about, gallery bands) | all staff |
| `/admin/reviews` | Reviews, with approval | all staff |
| `/admin/schedule` | Opening hours and closures | all staff |
| `/admin/messages` | Contact-form messages | all staff |
| `/admin/users` | Team | admin |
| `/admin/activity` | Audit trail | admin |
| `/admin/email-templates` | Email designs, revisions, publishing | admin |
| `/admin/settings` | Salon details, booking rules, email sending | admin |
| `/admin/account` | Own profile and password | everyone |

Uploaded images go to `UPLOAD_PATH` (the docroot's `uploads/` in production) under a random
filename; the database stores only that name. The path must be **outside** `public/` or
uploaded files become executable.

## Conventions

- Controllers do not touch SQL; they call repositories and services.
- Every `POST` is CSRF-checked by the dispatcher, not per route.
- Values reach templates through `e()`. A template that emits raw HTML says why.
- Tables state `utf8mb4_unicode_ci` explicitly — the host's default is `latin1`.
- Secrets live in `.env`, outside the webroot, `chmod 600`. `Logger` redacts known key names.
- The database session timezone is set from `APP_TIMEZONE`; without it `NOW()` disagrees
  with the salon's clock for part of every day.

## Where to read next

| Question | Document |
| --- | --- |
| How is this layered, and why? | [../docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md) |
| Every table and column | [../docs/DATA-MODEL.md](../docs/DATA-MODEL.md) |
| The JSON API | [../docs/API.md](../docs/API.md) |
| The booking rules | [../docs/BOOKING-RULES.md](../docs/BOOKING-RULES.md) |
| Deploying and rolling back | [../docs/OPERATIONS.md](../docs/OPERATIONS.md) |
| What staff should do | [../docs/ADMIN-HANDBOOK.md](../docs/ADMIN-HANDBOOK.md) |
