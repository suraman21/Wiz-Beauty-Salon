# WIZ — Admin Panel & API

Plain PHP 8.4 application. No framework, no ORM, no template engine.

Powers the staff admin panel at `/admin` and the JSON API at `/api/v1` that the
React site consumes. The public site itself is the separate React project
(`Wiz-Beauty-Salon`), served as static files from the same domain.

## Requirements

- PHP 8.2+ (production: 8.4)
- MariaDB 10.6+ / MySQL 8+
- Extensions: `pdo_mysql`, `gd`, `mbstring`, `json`, `sodium`, `fileinfo`
- Composer 2

## Setup

```sh
composer install
cp .env.example .env      # then fill in DB credentials and APP_KEY
chmod 600 .env
php database/migrate.php migrate
```

Generate the application key once:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

## Layout

| Path | Purpose |
|---|---|
| `src/Core` | Infrastructure: router, request/response, DB, session, CSRF, validation, auth |
| `src/Domain` | Enums and value objects — pure PHP, no I/O |
| `src/Contracts` | Repository interfaces |
| `src/Repositories` | PDO implementations of those contracts |
| `src/Services` | Business rules; the only place that may combine repositories |
| `src/Controllers/Admin` | Server-rendered admin pages (session auth) |
| `src/Controllers/Api` | JSON endpoints consumed by the React site |
| `src/Requests` | Input validation objects |
| `src/Views` | PHP templates |
| `config` | Configuration arrays backed by `.env` |
| `database` | Versioned migrations and the CLI runner |
| `deploy` | Files re-applied to the docroot on every React deploy |
| `public` | The only web-exposed folder — front controller only |
| `storage` | Logs, cache, sessions, private uploads, DB backups |

## Rules

- Controllers never write SQL. Services never build HTML.
- Prepared statements only. No string-interpolated SQL.
- Every output in a template goes through `e()` unless deliberately raw.
- Schema changes are migrations, never manual edits in phpMyAdmin.
- `.env` never enters git; `vendor/` is installed from `composer.lock`.

## Quality gates

```sh
composer lint       # php -l on all files
composer analyse    # PHPStan
composer unit       # PHPUnit
composer security   # composer audit
composer quality    # all of the above
```

## Deployment

Code lives at `/home/arkeonet/wiz-app` (outside the webroot). See
`WIZ-DEPLOYMENT.md` for the full procedure.
