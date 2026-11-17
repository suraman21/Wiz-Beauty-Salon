# Runbook

Symptoms first. Each entry says what to look at before what to change.

---

## The site is down

1. `curl -I https://wiz.arkeonethiopia.com/` — if this returns 200 from a terminal but not from a browser, suspect the CDN/proxy, not the app.
2. `curl -s https://wiz.arkeonethiopia.com/api/v1/services | head -c 80` — HTML here means the API rewrite is gone (the site itself will look perfect while the booking page silently uses the bundled fallback).
3. `tail -50 ~/logs/wiz-deploy.log` — did the last deploy fail its health check?
4. `ls -la ~/wiz.arkeonethiopia.com/` — is the docroot empty? A deploy that failed mid-rsync leaves the site gone, not stale.
5. `tail -50 ~/wiz-app/storage/logs/*.log` — the actual exception, with a request id.

Restore path: `deploy.sh --force` from the last good SHA, or the newest `~/backups/wiz/site-*.tar.gz`.

## The site loads, the booking page will not book

Almost always the API. Check, in order:

1. `/api/v1/services` returns JSON (if not: `.htaccess` rewrite missing, or `mod_rewrite` off).
2. `/api/v1/availability?date=<today>&service_id=1` returns `open: true` or a `reason` sentence. A `reason` is the answer, not a bug — print it.
3. The customer's browser console: a `429` means rate limits (see below), a `422` means validation (the message is human-readable).
4. `storage/logs` for `SlotUnavailable` — someone else took the slot while they were choosing. Normal under contention.

## `/admin` returns 403

`admin/public/.htaccess` denies dotfiles, not everything; a blanket 403 means the request is landing on the **app directory** rather than its `public/`. Confirm the docroot's shim (`index.php` requiring `../wiz-app/public/index.php`) exists and that `.htaccess` contains the `# BEGIN WIZ APP` block.

## `/admin` returns 500

1. `tail -100 ~/wiz-app/storage/logs/*.log` — usually a database credential or a missing migration.
2. `php ~/wiz-app/bin/preflight.php` — it checks connection, migrations, administrator, writable storage in one pass.
3. `DB_NAME` / `DB_USER` on cPanel include the account prefix (`arkeonet_wiz`), and the user must be **added to** the database, not just created.

## "We are not receiving booking emails"

1. Panel → **Settings → Email → Send a test**. If it fails, the credentials are wrong, not the code.
2. `storage/logs` — `Mailer` records what it tried and redacts the password.
3. Check the salon's spam folder and that `MAIL_SALON_EMAIL` is the address they actually read.
4. The booking itself is never lost to a mail failure: it is in `bookings` and on the **Bookings** screen regardless.

## Customers say their manage link "does not exist"

A link is valid for **24 hours** and expires. That is by design and the terms say so. Two fixes, both normal:

- Point them at `/booking/recover` — phone or email, five tries per five minutes.
- Or copy a fresh link from the booking's page in the panel.

A link that was working *minutes* ago and now 404s usually means `APP_KEY` changed. `deploy.sh` generates a key only when one is missing, so a replaced or restored `.env` is the likely cause — compare `~/wiz-app/.env` with the backup in `~/backups/wiz/`. If the key really was lost, every outstanding link is dead: say so plainly to the salon rather than letting them discover it one customer at a time.

## Two customers, one slot

The database prevents it: `booking_slots` has `PRIMARY KEY (slot_start)`. If you are looking at two bookings for the same time, one of them is in a non-occupying status (`cancelled`, `declined`, `no_show`) and its slots were released — check `booking_status_history` for that booking id.

## The calendar offers a day the salon is closed

Check, in this order: **Opening hours** (the weekly row for that weekday), **Closures** (a range covering the day), then the settings' `booking.max_advance_days`. The availability endpoint's `reason` names which one it is.

## Someone left the salon and still has access

Panel → **Team** → deactivate. That ends the session and blocks sign-in immediately; nothing is deleted, and their name stays on the history they wrote.

If the account cannot be reached (locked out), an admin resets the password from the same screen.

## "The website shows an old price / old photo"

Up to 60 seconds of server cache, plus whatever the browser holds. A hard refresh is the check. If it is still old after a minute, the change never saved — look at **Activity** for the edit.

## Disk full

`df -h ~`. Usual causes: `storage/logs`, `~/backups/wiz` (10 site + 10 app tarballs), uploads, or `~/wiz-deploy/.git` growing. `php ~/wiz-app/bin/housekeeping.php` clears the first; the rest is a decision about what to keep.

## A deploy is stuck or two are fighting

The script uses `flock /tmp/wiz-deploy.lock`. A stale lock after a killed run is cleared with `rm /tmp/wiz-deploy.lock` — but check `ps aux | grep deploy.sh` first, because a slow migration is not a stale lock.

## The site is fine, the panel is slow

Look at `bookings` — no index is missing, but an unfiltered screen on a multi-year table is slow by design. `bookings_status_day` covers the common query; if the panel is slow on the **Bookings** screen specifically, check the pagination is being used rather than loading everything.

---

## Known gaps

These are decisions not yet taken, not mysteries:

| Gap | Consequence | Workaround today |
| --- | --- | --- |
| No Content-Security-Policy on the panel's HTML | a hypothetical XSS in the panel is not contained by CSP | headers otherwise hardened; `Kernel::harden()` is the place to add it |
| No automated off-site backup | a disk loss loses bookings and photos | manual cron in `docs/OPERATIONS.md` § Backups; **do this** |
| No uptime monitor | the salon learns from customers | any external monitor on `/api/v1/services` |
| No CDN cache-control tuning for uploads | repeat views re-download photos | `Cache-Control` is set for API responses, not for `/uploads` |
| Manage links expire in 24 h | a customer booking far ahead needs a fresh link | `/booking/recover`; see `docs/BOOKING-RULES.md` § 10 |
