# Operations

Everything between "the tests pass" and "the salon is using it": how a release is built, how it reaches the server, how it is verified, how it is undone, and what has to be backed up before any of that matters.

The hosting-specific steps (creating the subdomain, the database, the `.env`) are in **`docs/HOSTING-CPANEL.md`**. This document assumes that setup exists.

---

## The shape of a release

```
push to main
   └─ CI (.github/workflows/ci.yml)
        ├─ site job      lint → typecheck → 104 tests → build
        ├─ admin job     php -l → PHPStan → 211 tests → migrations → audit
        ├─ security job  npm audit (advisory) → gitleaks (full history)
        └─ publish job   assemble + composer install --no-dev
                          → force-orphan `deploy` branch
                               site/    prerendered HTML, assets  → docroot
                               admin/   PHP app with vendor/      → ~/wiz-app
                               RELEASE  the main commit SHA
   └─ server cron (every minute)
        └─ ~/wiz-deploy/deploy.sh  → backup → reset to origin/deploy → rsync → migrate → health check
```

`main` is source. `deploy` is a build product, rewritten rather than merged, always exactly one commit. The server never sees `main`.

Why a branch and not a push-to-server: the server holds no deploy key, so there is nothing on it worth stealing for a supply-chain attack; and `deploy` is inspectable — you can see what is live without SSH.

---

## What the publish job ships

- `site/` — **8 prerendered pages** (`/`, `/services`, `/gallery`, `/about`, `/contact`, `/booking`, `/booking/recover`, `/booking/information`), each a directory with its own `index.html`, plus hashed assets, `404.html`, `_headers`, `boot.js`, `robots.txt` and the icons. The `?service=` deep links are the same `/booking` document. No `node_modules`, no `.output/server`, no Node runtime.
- `admin/` — the PHP application **with `vendor/` installed** (`composer install --no-dev`), because the host has no Composer. `tests/`, `.env` and `.gitignore` are removed on purpose.
- `RELEASE` — the commit SHA this tree was built from.

---

## Deploying

**Automatic (normal case).** Push to `main`; CI publishes `deploy`; the cron picks it up within a minute.

```cron
* * * * * /home/arkeonet/wiz-deploy/deploy.sh >> /home/arkeonet/logs/wiz-deploy.log 2>&1
```

**By hand**, when you want it now and want to watch:

```bash
cd ~/wiz-deploy && bash deploy.sh
```

The script takes a `flock`, so a manual run and the cron cannot overlap. It will:

1. Fetch `origin/deploy` and compare SHAs — nothing to do means nothing done.
2. Back up the docroot and the app to `~/backups/wiz/`, keeping the last **10** of each.
3. `rsync --delete` the site into the docroot and the app into `~/wiz-app`, **excluding** `.env`, `storage/uploads`, `storage/backups` — files that live on the server are never owned by a release.
4. Merge its rules into the existing `.htaccess` between `# BEGIN WIZ APP` / `# END WIZ APP`, stripping any legacy block first.
5. Run migrations with the cPanel PHP binary.
6. Health-check the **origin** (never through Cloudflare, which would answer for a dead site): `/`, `/api/v1/services`, `/admin/login`.

Non-zero exit means something above failed; the log line names which.

---

## Verifying a release actually took

```bash
curl -I  https://wiz.arkeonethiopia.com/                       # 200, text/html
curl -s  https://wiz.arkeonethiopia.com/api/v1/services | head -c 120   # JSON
curl -I  https://wiz.arkeonethiopia.com/admin/login             # 200
cat /home/arkeonet/wiz-app/RELEASE                              # should equal origin/main's SHA
php /home/arkeonet/wiz-app/bin/preflight.php                    # 0 failures
```

`bin/preflight.php` is the checklist in executable form: PHP version and extensions, `.env` present and not world-readable, `APP_KEY` set, upload and storage paths writable, database reachable, **every migration applied**, at least one active administrator, and whether this tree looks like a release.

**A green site is not proof the API works.** If `/api/v1/services` returns HTML, the rewrite did not merge and the booking page is quietly using its bundled fallback catalogue. Always check the JSON.

---

## Deploying by hand, without SSH

If the server has no shell (or the cron is not set up yet), the same release can be uploaded
from cPanel's File Manager. This is the fallback, not the normal path — it is slower and it
skips the backups and the health check.

1. Download the `deploy` branch as a ZIP (GitHub → *Code → Download ZIP* on that branch) and
   note the SHA in `RELEASE`.
2. Upload and extract it so that `site/` becomes the docroot's contents:
   `site/index.html` → `public_html/index.html`, `site/assets/` → `public_html/assets/`.
   The routes are directories — `site/booking/index.html` becomes
   `public_html/booking/index.html`.
3. Extract `admin/` to `~/wiz-app`, keeping the existing `.env` and `storage/uploads` — never
   overwrite those two.
4. Add the rewrite block from `admin/deploy/htaccess` to the docroot's `.htaccess` between
   `# BEGIN WIZ APP` and `# END WIZ APP`, and the shim `index.php` beside it.
5. Run the migrations over cPanel's Terminal if it offers one, otherwise via cron-once:
   `/opt/cpanel/ea-php84/root/usr/bin/php ~/wiz-app/database/migrate.php migrate`.
6. Verify exactly as above — `curl -s .../api/v1/services` must return JSON.

If `/api/v1/services` returns HTML after a manual upload, the rewrite did not land; the site
will look perfect while the booking page quietly uses its bundled fallback catalogue.

---

## Rolling back

The five-minute version, for a code fault:

```bash
cd ~/wiz-deploy
git log --oneline -3 origin/deploy        # find the last good commit
git reset --hard <sha> && bash deploy.sh --force
```

But `deploy.sh` always resets to `origin/deploy` — so the durable way is to revert on `main`, which rebuilds and republishes:

```bash
git revert <bad-commit>     # never force-push, never rewrite published history
git push origin main        # CI republishes `deploy`; the cron follows
```

Database: migrations are reversible (`php database/migrate.php rollback` undoes the last one), but a rollback after data has been written is a decision, not a command — check `down()` before running it. The safe order is: revert the code, leave the schema, and only roll the schema back if the revert requires it.

Files: every deploy leaves a tarball. Restoring the docroot is

```bash
tar xzf ~/backups/wiz/site-YYYYmmdd-HHMMSS.tar.gz -C ~/wiz.arkeonethiopia.com
```

---

## Backups

**Nothing in this repository backs up automatically. That is a gap the salon must close deliberately.** The two things that matter:

1. **The database** — bookings, customers, settings, designs.
2. **`~/wiz-app/storage/uploads/`** (and the docroot's `uploads/`, same files) — every picture the salon has uploaded.

A dump without the uploads is a site full of missing photos; uploads without the dump is a gallery nobody can reach. Back up both or neither.

Suggested cron on the server (cPanel → Cron Jobs), keeping 14 days:

```bash
mkdir -p ~/backups/db
mysqldump --single-transaction --quick ~/wiz-app/.env … > ~/backups/db/wiz-$(date +%F).sql
tar czf ~/backups/db/uploads-$(date +%F).tar.gz -C ~/wiz-app/storage uploads
find ~/backups/db -mtime +14 -delete
```

Read the credentials out of `.env` rather than typing them into a command that lands in shell history. Then **copy the directory off the server** — a backup on the same disk as the thing it protects is not a backup. cPanel's own backup tool, or any object storage, is enough.

Restore drill (do this once, on a copy):

```bash
mysql -u USER_wiz -p USER_wiz < ~/backups/db/wiz-2026-11-17.sql
tar xzf ~/backups/db/uploads-2026-11-17.tar.gz -C ~/wiz-app/storage
php ~/wiz-app/bin/preflight.php
```

---

## Monitoring

There is no uptime monitor configured. What exists:

- `storage/logs/` — application errors with a request id; pruned after `LOG_RETENTION_DAYS` (90).
- The **Activity** screen — `activity_log`, kept `AUDIT_RETENTION_DAYS` (365).
- `~/logs/wiz-deploy.log` — every deploy, its health check and its exit code.
- Email to `MAIL_SALON_EMAIL` — every new booking and every contact message.

What is missing, and cheap to add: an external monitor hitting `/api/v1/services` every five minutes and emailing on failure. Any free service will do; the endpoint returns JSON with a 200 and touches the database, so it fails when the app or the database fails.

---

## Routine maintenance

| When | What |
| --- | --- |
| Every deploy | watch the health check; run `bin/preflight.php` |
| Weekly | clear the dashboard's *Requested* list; check **Messages** |
| Monthly | confirm the cron is still running (`tail ~/logs/wiz-deploy.log`); check free disk (`df -h ~`) |
| Quarterly | restore a backup into a scratch database and confirm it opens |
| Yearly | rotate the database password and `MAIL_*`; `APP_KEY` only if every outstanding customer link may be invalidated |

`php bin/housekeeping.php` prunes expired sessions, old login attempts, rate-limit rows and oversized logs. Run it from cron nightly:

```cron
15 3 * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/arkeonet/wiz-app/bin/housekeeping.php >> /home/arkeonet/logs/wiz-housekeeping.log 2>&1
```

---

## Release checklist

- [ ] CI green on `main` — all four jobs, not three.
- [ ] `deploy` branch's `RELEASE` matches the `main` SHA you intend to ship.
- [ ] Migrations ran (deploy log says `migrations: up to date`; `bin/preflight.php` says `22 of 22`).
- [ ] `/api/v1/services` returns JSON, not HTML.
- [ ] `/admin/login` loads and you can sign in.
- [ ] One booking made end-to-end from a phone, on mobile data, then cancelled from the emailed link.
- [ ] `/booking/information` opens in both languages.
- [ ] No new warnings in `storage/logs/`.

---

## Secrets

`.env` holds the database password, `APP_KEY` and the SMTP credentials. It lives at `~/wiz-app/.env`, mode `600`, outside the docroot, and no release ever overwrites it. It is **never** committed; `.gitleaks.toml` and the CI secret scan exist to keep it that way.

If a secret leaks: rotate it at the source first (database password, SMTP password), then update `.env`, then `php bin/preflight.php`. If `APP_KEY` is the one that leaked, every customer link in every inbox stops working — tell the salon before you do it. See `SECURITY.md`.
