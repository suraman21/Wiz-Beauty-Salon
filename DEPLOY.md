# Deploying

## What CI produces

A push to `main` runs `.github/workflows/ci.yml` (site, admin, security). If all
three pass, the `Publish deploy branch` job assembles the release tree and pushes
it to the orphan `deploy` branch:

```
deploy/
├── RELEASE        # the main commit SHA this tree was built from
├── site/          # the prerendered public site — static HTML, no Node
└── admin/         # the PHP panel, with vendor/ already installed
```

`site/` is plain files: 7 pages (`/`, `/services`, `/gallery`, `/about`,
`/contact`, `/booking`, `/booking/recover`), each a prerendered `index.html`,
plus hashed assets under `site/assets/`. Nothing needs to run. `.output/server`
from the build is **not** part of the release — the site needs no Node process.

Do not deploy straight from `main`; `src/` needs a build. The `deploy` branch is
the only branch that is ready to serve as-is.

## Why nothing shipped before

`Publish deploy branch` requires `needs: [site, admin, security]`. The admin job
had been failing at PHPStan, so the deploy branch was never written, even when
the site built successfully. That is fixed.

## Uploading to cPanel

1. Take `RELEASE` from the branch tip and confirm the SHA matches the `main`
   commit you intend to ship.
2. Upload the **contents** of `site/` to the document root of the public domain
   (`public_html`), so that `site/index.html` becomes `public_html/index.html`
   and `site/assets/` becomes `public_html/assets/`.
3. Upload `admin/` to its own directory, e.g. `public_html/admin/`.
4. Keep the two separate: `admin/` must not sit inside a path the SPA rewrites.
5. The site is prerendered into directories, so unknown paths must not fall
   through to the home page. For Apache:

```apache
DirectoryIndex index.html
ErrorDocument 404 /404.html
```

## The admin panel needs

- PHP 8.4 with `gd`, `pdo_mysql`, `mbstring`, `intl` and `dom`.
- A MySQL/MariaDB database, with `admin/.env` populated (`DB_*`, `APP_KEY`).
  Generate the key with `php -r 'echo bin2hex(random_bytes(32));'`.
- `vendor/` is shipped in the release, so no Composer on the server. PHPMailer
  and HTML Purifier are included; SMTP and the email design studio depend on
  them.
- Run `php database/migrate.php migrate` once after the first upload, and
  `php database/seed.php "Name" you@example.com "password"` to create the first
  administrator.

`admin/tests` and `admin/.gitignore` are removed from the release on purpose.

## Verifying a release

```bash
bun install --frozen-lockfile
bun run lint
npx tsc --noEmit
bun run test
bun run build          # writes .output/public
```

Then serve `.output/public` over HTTP and check all 7 routes return 200 with no
hydration warnings in the console and no broken images. Serving over `file://`
will not work: the routes are directories and the assets are absolute.
