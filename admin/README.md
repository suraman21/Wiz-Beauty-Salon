# WIZ Beauty Salon — admin panel

Plain PHP 8.4 and MariaDB. No framework, no ORM, no template engine. The public
website stays React; this folder powers `/admin` and, from Phase 2, `/api/v1`.

```
admin/
├── bootstrap/        autoloader + service container
├── config/           app, database, mail defaults (read from .env)
├── database/         migrations, migrate.php, seed.php
├── public/           front controller + assets (only public/ would ever be web-facing)
├── routes/           admin.php, api.php
├── src/
│   ├── Controllers/  one class per screen
│   ├── Core/         App, Router, Request, Response, View, Session, Csrf, ...
│   ├── Database/     migration contract
│   ├── Domain/       enums shared with the public API
│   ├── Repositories/ every SQL statement lives here
│   ├── Services/     rules that span more than one repository
│   ├── Support/      helper functions used by templates
│   └── Views/        plain PHP templates
├── storage/          logs, sessions, cache, uploads (never committed)
└── tests/            PHPUnit
```

## First run

```bash
cp .env.example .env
chmod 600 .env          # fill in DB_* first

composer install                                   # optional: dev tools only
php database/migrate.php migrate                   # create the schema
php database/seed.php "Your Name" you@example.com  # creates the administrator
php -S 127.0.0.1:8080 -t public public/index.php    # http://127.0.0.1:8080/admin
```

Without Composer the app still boots — `bootstrap/autoload.php` falls back to a
PSR-4 loader. Composer is needed only for PHPStan and PHPUnit.

## Checks

```bash
composer lint      # php -l on every file
composer analyse   # PHPStan level 6
composer unit      # PHPUnit
composer quality   # all of the above, plus composer audit
```

CI runs the same suite on every push, plus the migrations against a real
MariaDB 11.4 instance.

## Conventions

- Controllers do not touch SQL; they call repositories and services.
- Every `POST` is CSRF-checked by the dispatcher, not per route.
- Values reach templates through `e()`. A template that emits raw HTML does so
  deliberately and says why.
- Tables are created with `utf8mb4_unicode_ci` explicitly — the database default
  on this server is `latin1`.
- Secrets live in `.env`, outside the webroot, `chmod 600`. Nothing sensitive is
  written to the log: `Logger` redacts known key names.
