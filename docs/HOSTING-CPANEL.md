# cPanel setup — new subdomain

Written for this repo as it actually is: static site in the docroot, PHP app
outside it, and `admin/deploy/deploy.sh` doing the work. Nothing here is a manual
copy step unless you want it to be.

Throughout, substitute:

| Placeholder | Meaning                   | Example                |
| ----------- | ------------------------- | ---------------------- |
| `USER`      | your cPanel account name  | `arkeonet`             |
| `SUBDOMAIN` | the subdomain you created | `wiz.example.com`      |
| `DOCROOT`   | its document root         | `/home/USER/SUBDOMAIN` |

The repo's own defaults assume `USER=arkeonet` and
`SUBDOMAIN=wiz.arkeonethiopia.com`. If yours are different, see
[Paths that are hardcoded](#paths-that-are-hardcoded) — it is the one thing that
will not just work.

---

## 0. The clone you just made is probably in the wrong place

`main` is source, not a build. It has no HTML to serve, and if you cloned it into
the document root, `https://SUBDOMAIN/.git/config` is publicly readable and
`src/`, `admin/composer.lock` and friends are downloadable. Move it out:

```bash
# if the clone is sitting in the docroot
mv /home/USER/SUBDOMAIN /home/USER/wiz-deploy
mkdir -p /home/USER/SUBDOMAIN          # recreate the docroot, empty
```

`wiz-deploy` is a **checkout of the `deploy` branch**, not `main`. You do not
need to switch it by hand — the script fetches and resets to `origin/deploy`.
Keeping it in a scratch directory like this also means a `git pull` can never
publish raw source by accident.

If you used cPanel's _Git Version Control_, note it clones `main` by default;
the directory still works as `wiz-deploy`, but do not point the subdomain's
document root at it.

## 1. PHP 8.4 and the extensions

**MultiPHP Manager** → set `SUBDOMAIN` to **PHP 8.4 (ea-php84)**.
**MultiPHP INI Editor / Select PHP Version → Extensions** → ensure these are on:

```
fileinfo  gd  mbstring  pdo  pdo_mysql  sodium
```

`json` is built in. The app needs no `intl` and no Composer on the server — the
release ships `vendor/`.

Verify over SSH/Terminal:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -v
/opt/cpanel/ea-php84/root/usr/bin/php -m | grep -E 'gd|pdo_mysql|mbstring|fileinfo|sodium'
```

That binary path is what `deploy.sh` calls, so if it is missing, nothing will
migrate.

## 2. Create the database

**cPanel → MySQL® Databases** (not the wizard, if you want to keep names short):

1. **Create New Database** → `wiz` → becomes `USER_wiz`.
2. **Add New User** → `wiz` → strong password → becomes `USER_wiz`.
3. **Add User To Database** → select both → grant **ALL PRIVILEGES**.

Host stays `localhost`. Write the three values down exactly as cPanel shows
them, including the `USER_` prefix — the prefix is part of the name, not a
display detail.

You do not create any tables. Migrations do that.

## 3. Create `.env` before the first deploy

The app directory does not exist yet, and the deploy script keeps `.env` (it is
excluded from its `rsync --delete`), so create it first:

```bash
mkdir -p /home/USER/wiz-app
cp /home/USER/wiz-deploy/admin/.env.example /home/USER/wiz-app/.env
chmod 600 /home/USER/wiz-app/.env
nano /home/USER/wiz-app/.env
```

Fill in at minimum:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SUBDOMAIN
APP_KEY=                                   # leave blank — deploy.sh generates it

DB_HOST=localhost
DB_NAME=USER_wiz
DB_USER=USER_wiz
DB_PASS=the-password-you-set

SESSION_SECURE=true                        # requires HTTPS on the subdomain

UPLOAD_PATH=/home/USER/SUBDOMAIN/uploads
UPLOAD_URL=/uploads
```

Leave `APP_KEY` empty on purpose: the script generates one and appends it if it
is missing, and it must only ever be generated once — it signs the cancel and
reschedule links already sitting in customers' inboxes.

Optional, for email: `MAIL_*` and `MAIL_SALON_EMAIL`. You can also set these
later in the panel's Settings screen, which does not need a redeploy.

## 4. First deploy

From the clone, run the script that lives inside it:

```bash
cd /home/USER/wiz-deploy
bash admin/deploy/deploy.sh --force
```

Expect it to: back up the docroot, reset to `origin/deploy`, install the app into
`/home/USER/wiz-app`, create `storage/*`, sync the built site into the docroot,
install the front-controller shim, merge its rules into `.htaccess`, generate
`APP_KEY`, and run migrations.

Two things it will tell you:

- `WARNING: /home/USER/wiz-app/.env is missing` — you skipped step 3.
- `WARNING: /admin/login returned 000` — see
  [If the health check fails](#if-the-health-check-fails).

On the first run it also installs itself to `/home/USER/wiz-deploy/deploy.sh`,
which is the path cron uses later.

## 5. Create the administrator

Migrations create the schema; this creates the login:

```bash
cd /home/USER/wiz-app
/opt/cpanel/ea-php84/root/usr/bin/php database/seed.php \
  "Salon Administrator" you@example.com "a-long-password"
```

It reads `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` if you omit
the arguments. Re-running it with the same email refuses unless you add
`--force`, which resets the password instead of duplicating the account.

## 6. Check it

```bash
curl -I  https://SUBDOMAIN/                       # 200, text/html
curl -s  https://SUBDOMAIN/api/v1/services | head -c 120   # JSON, not HTML
curl -I  https://SUBDOMAIN/admin/login            # 200
curl -I  https://SUBDOMAIN/gallery                # 200
```

Then sign in at `https://SUBDOMAIN/admin/login`.

**Important:** if `/api/v1/services` returns HTML instead of JSON, the API is not
routed and the site will still look perfect — the booking page silently falls
back to the catalogue bundled into the build. Do not mistake that for success.
A working API means the booking page shows the salon's live services and prices.

## 7. Automatic deploys (optional)

```bash
crontab -e
```

```cron
* * * * * /home/USER/wiz-deploy/deploy.sh >> /home/USER/logs/wiz-deploy.log 2>&1
```

Every push to `main` that passes CI republishes `deploy`, and the site follows
within a minute. The script self-updates from the release, keeps the last 10
backups, and takes a lock so two runs cannot overlap.

---

## Paths that are hardcoded

`admin/deploy/deploy.sh` does not take overrides. These constants assume the
`arkeonet` account:

```bash
REPO_DIR="/home/arkeonet/wiz-deploy"
DOCROOT="/home/arkeonet/wiz.arkeonethiopia.com"
APPDIR="/home/arkeonet/wiz-app"
PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"
```

The health check at the end also probes `https://wiz.arkeonethiopia.com`
literally.

Edit them in the **committed** copy — `admin/deploy/deploy.sh` — and push. Do not
edit `~/wiz-deploy/deploy.sh` on the server: the script adopts the committed
version on every run and your edit will be silently replaced.

On a different subdomain the deploy still succeeds; only the last checks fail
loudly and the exit code is non-zero.

## If the health check fails

The script probes the **origin**, not through Cloudflare, precisely because the
CDN answers for a dead site. Relevant cases:

- `/` returns 500 but files exist → PHP is running the shim and the app cannot
  boot. Read `/home/USER/wiz-app/storage/logs/`.
- `/` returns 200 but `/api/v1/services` returns HTML → `.htaccess` did not merge.
  Check for a `# BEGIN WIZ APP` block in the docroot `.htaccess`, and that
  `mod_rewrite` is enabled.
- `/admin/login` returns 403 → `admin/public/.htaccess` is doing its job; the
  shim is missing from the docroot. Re-run the deploy.
- Everything 200 but pages are unstyled → the docroot is serving `index.html`
  without `assets/`. The site's files were not synced.
- Database errors in the panel → `DB_NAME` / `DB_USER` missing the `USER_`
  prefix, or the user was created without being added to the database.

## Why the layout is split

The PHP app is deliberately **not** in the webroot:

```
/home/USER/SUBDOMAIN/     docroot — built HTML, assets, uploads, index.php shim, .htaccess
/home/USER/wiz-app/       PHP app, .env, storage/, vendor/ — not reachable over HTTP
```

`admin/public/.htaccess` denies all access, so even a misconfigured document root
pointing at `admin/public` fails closed. The docroot holds a one-line shim that
`require`s `../wiz-app/public/index.php`, and the rewrite rules send `/api`,
`/admin` and `/booking/manage` to it while leaving real files alone.
