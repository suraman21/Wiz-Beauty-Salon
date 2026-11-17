# Pre-production audit — WIZ Beauty Salon

**Date:** 17 November 2026
**Commit under test:** `acd9e1d` (CI run 44117675853 — site, admin and security all green; deploy branch published as `32b4d90`)
**Environment:** PHP 8.4.27, MariaDB 11.8.6, Node 20.20.2, Chromium (headless shell 159), Chromium viewports 1280×900 and 390×844
**Verdict:** ship-blocking defects found in this pass have been fixed and re-verified. Section 7 lists what is **not** covered and what is still missing before go-live.

---

## 1. Method

The audit was done against a freshly built environment, not against the developer's
machine:

1. Fresh database (`wiz`), migrations applied from scratch (21 migrations), catalogue
   seeded (14 service categories, 78 services), administrator created.
2. Both servers started (panel/API on `:8080`, site on `:5173`) and every page opened in
   a real browser.
3. Every workflow walked end to end in the browser, then checked again in the database.
4. Security checks done twice — by reading the code, and by attacking the running server
   with `curl` and Playwright.
5. Full gate re-run after every change (section 3).

Anything in this report that says "verified" was executed in this environment during
this pass. Anything that says "not covered" was not.

---

## 2. What was fixed in this pass

### 2.1 No security headers anywhere — **High** — fixed

**Found:** no `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy` or HSTS on any response. The panel could be
framed by another site and clicked through by a signed-in member of staff (clickjacking),
and JSON answers could be sniffed as HTML.

**Fixed:** `admin/src/Core/Kernel.php` gained `harden()`, applied to **every** response
through `handle()`, so a new controller cannot forget it:

| Header | Value |
| --- | --- |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` (API responses only) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (only when the request arrived over HTTPS) |

`Request::server()` was added so the kernel can see `HTTPS` without reaching into globals.

**Verified:**

```
$ curl -sI http://127.0.0.1:8080/api/v1/salon
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Cross-Origin-Opener-Policy: same-origin
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
```

The same headers appear on the panel's HTML (`/admin/login`).

### 2.2 `admin/public/.htaccess` denied the entire panel — **High** — fixed

**Found:** the file contained `Require all denied`, copied from the rules meant for the
application directory, with a comment explaining it was "a safety net in case `public/`
is ever mapped as a document root by mistake". It *is* the document root. On any Apache
that honours `.htaccess` this is a 403 on every page of the panel; on this host it is
silently ignored, which is why the defect survived. Either way the file did nothing
useful.

**Fixed:** rewritten to serve the panel, block dotfiles (`.env`, editor backups), deny
PHP execution in any directory that might one day be created under the webroot, and set
the browser headers (`X-Powered-By` unset as well, to stop advertising the PHP version).

### 2.3 The documented 404 page did not exist — **Medium** — fixed

**Found:** `DEPLOY.md` instructs `ErrorDocument 404 /404.html`, but the build never
produced that file, so every wrong address fell back to Apache's default page.

**Fixed:** `public/404.html` — a branded page with the way back into the site. It is
copied into the release by the build.

### 2.4 The booking gap was a setting with no way to change it — **Medium** — fixed

**Found:** `booking.slot_minutes` (30), `booking.lead_minutes` (120) and
`booking.max_advance_days` (60) were already read from the database on every request, but
nothing in the panel wrote them. Changing the 30-minute gap meant editing SQL.

**Fixed:** a **Booking rules** card on `/admin/settings` (`POST /admin/settings/booking`)
with server-side validation: gap 5–120 minutes, notice 0–4320 minutes, horizon 1–365
days. Each field explains what it does in the salon's own words, and the save writes an
activity-log entry.

**Verified** in the browser and through the API:

| Gap | Times offered (opening 09:00) | Single service | Two services |
| --- | --- | --- | --- |
| 30 | 09:00, 09:30, 10:00 … | 30 min | 60 min |
| 45 | 09:00, 09:45, 10:30, 11:15 … | 45 min | 90 min |

### 2.5 Several services in one booking, from the website — **Medium** — fixed

**Found:** the schema, the panel and `BookingService` already supported a list of
`services`, but the public site books **categories** (`category_id`), and no endpoint
accepted a list of those. So a customer could not book two treatments in one visit —
the case the salon asked for.

**Fixed, end to end:**

- `GET /api/v1/calendar?category_ids=118,119` — the run is the sum of the chosen
  services (each keeps the salon's gap, so the total always lands on the grid), and the
  answer carries `service.duration_minutes` for the whole run.
- `POST /api/v1/bookings` accepts `category_ids` (JSON or comma-separated), capped at
  six per booking, each id re-checked for existence and `is_active`.
- `BookingService::create()` resolves the list, refuses an unavailable service, refuses a
  run longer than 480 minutes, and stores the joined name (`Make Up + Nail`), the ids and
  the total length.
- The site's booking page is now a multi-select: tap several services, the page shows
  `2 services — Make Up + Nail · about an hour`, the calendar reloads for the longer run,
  and both the summary and the confirmation list them.
- The panel already displayed `N services · 60 min`; a booking detail now reads
  *"Make Up + Nail — booked together, back-to-back"* and keeps confirm / decline /
  cancel / move.

**Verified:** booking `WZ261117-ZM35` created from the browser —
`service_name = "Make Up + Nail"`, `service_ids = [119,118]`, `duration_minutes = 60`,
`09:00–10:00`. It appeared in the panel list and detail page, and cancelling it from the
panel freed both grid cells (`booking_slots` back to 0 for that day).

### 2.6 The other pages now open with the home page's photograph — **Medium** — fixed

**Found:** each inner page's hero used a different photograph, so the site felt like five
different sites.

**Fixed:** `src/lib/hero-art.ts` is now the single import of
`hero-wiz-purple.jpg` — the home page and the new `HeroBand` component both use it, so
they cannot drift apart. The band is a strip 340 px tall on a 1280×900 screen and 133 px
on a 390 px phone (the home hero is nearly a full viewport), inset inside the page
measure, framed with a hairline and a plum veil.

**The crop was the risky part.** The photograph is 1672×941 with the tiara in the top 3 %
and the face ending around 32 %. Covering a 5:1 strip removes height only, and the frame
is pinned to `object-position: 50% 0%`, so the tiara, the face, the flowers and the top of
the gown are all inside the band at every width tested (1280, 1192 band width, 390):
nothing important is cut. Styles are in `src/styles.css` under "The shared hero band".

---

## 3. The gate

Run in this environment after the last change.

| Check | Command | Result |
| --- | --- | --- |
| TypeScript | `tsc --noEmit` | clean |
| Lint | `eslint .` | clean |
| Site unit tests | `vitest run` | 9 files, **95 tests** passed |
| Site build | `vite build` | OK, all 7 routes prerendered |
| PHP syntax | `php -l` on every changed file | clean |
| PHP static analysis | `phpstan analyse` | `[OK] No errors` |
| PHP tests | `phpunit` | **204 tests, 25 324 assertions**, OK |

New tests added in this pass:

- `BookingFlowTest::testBooksSeveralServicesAsOneAppointment`
- `BookingFlowTest::testTheWholeRunHasToFitInOneSlot`
- `BookingFlowTest::testRefusesASecondServiceThatIsNoLongerOffered`
- `BookingFlowTest::testOneChosenServiceIsTheSameAsTheOldSingleBooking`
- `BookingFlowTest::testTheGapBetweenTimesComesFromSettings`
- `booking-api.test.ts`: multi-service calendar request, single-service compatibility,
  `category_ids` in the booking body

---

## 4. Security tests

All executed against the running server.

| Test | Expected | Result |
| --- | --- | --- |
| `POST /admin/settings/booking` with no CSRF token | 419 | **419** |
| `POST /api/v1/bookings` with `Origin: https://evil.example` | 403 | **403** |
| Booking honeypot field filled | 422, no record | **422** |
| `/admin/bookings/55` signed out | redirect to login with `next` | **302 → `/admin/login?next=…`** |
| 7 booking attempts in 60 s from one address | 429 after 5 | **201, 422, 422, 429, 429, 429, 429** |
| `<script>alert(1)</script>` in a booking's notes | stored as text, never executed | stored escaped in the DOM, **no dialog** |
| `?category_id=118 OR 1=1` | treated as `118` | **200, one valid day set** |
| `/admin/bookings/55' OR '1'='1` | 404 / 302, never SQL | **302** |
| Unknown path | 404 | **404** |

### Checked by reading the code, found in order

- **Output escaping:** every `<?= $…` in the panel's views goes through `e()`; no raw
  interpolation found in `admin/src/Views`.
- **CSRF:** `CsrfMiddleware` is applied by the kernel to *every* POST outside `/api/`
  (42 forms audited; the two without a token are `method="get"`, which is correct).
- **Authentication:** `auth` / `admin` middleware on every panel route; `AuthService`
  throttles per account+IP and per IP, and logs failures.
- **IDOR:** the only public object access is `booking/manage/{token}`, and the token is an
  HMAC-signed `purpose:id:expiry` pair verified with `hash_equals`, so an id alone
  reveals nothing.
- **Uploads:** `Uploader` decides the type from the bytes (`finfo` + `getimagesize`),
  stores outside the webroot, names the file from `random_bytes`, and derives the
  extension from the detected type — `photo.php.jpg` cannot survive.
- **SQL:** all queries are prepared statements with bound parameters; the only string
  building found was ORDER BY from a fixed whitelist.
- **Secrets:** no `.env`, key or token in tracked files (`admin/.env.example` only). The
  hit in `admin/public/assets/email-editor.js` is minified vendor code.
- **Sessions:** `httponly`, `samesite=Lax`, `secure` in production, regenerated on login.
- **Errors:** `display_errors` off unless debug is on; unhandled exceptions become a 500
  with a short reference and the detail goes to the log.

---

## 5. Workflows walked end to end

| # | Workflow | Result |
| --- | --- | --- |
| 1 | Home page: hero, mood photos, services, gallery, reviews, footer | OK |
| 2 | Services page: category filter, service cards, link into booking | OK |
| 3 | Gallery: grid, before/after, lightbox | OK |
| 4 | About page | OK |
| 5 | Contact: details, hours, closures, map, message form | OK |
| 6 | Booking: pick one service → date → time → details → confirm | OK |
| 7 | Booking: pick **two** services → longer run → confirm | OK |
| 8 | Booking recover by phone / email | OK |
| 9 | Customer manage link: move and cancel a booking | OK (previous pass, unchanged code path) |
| 10 | Panel login / logout / wrong password / lockout | OK |
| 11 | Dashboard: day schedule, Ethiopian labels, counts | OK |
| 12 | Bookings list: filters, day grouping, `N services · M min` | OK |
| 13 | Booking detail: confirm, decline, cancel, move, staff note, history | OK |
| 14 | Schedule view: Ethiopian inputs, closures | OK |
| 15 | Services (categories) CRUD, image, home-page flag | OK |
| 16 | Gallery upload, reorder, home-page flag | OK |
| 17 | Website images slots | OK |
| 18 | Reviews moderation | OK |
| 19 | Opening hours and closures | OK |
| 20 | Messages inbox | OK |
| 21 | Team management | OK |
| 22 | Activity log | OK |
| 23 | Settings: mail, salon details, **booking rules**, test email | OK |
| 24 | Email designs studio | OK |
| 25 | Public API: services, gallery, reviews, salon, images, availability, calendar, bookings, recover, messages | OK |
| 26 | 404 page, unknown route, wrong method | OK |

---

## 6. Production-readiness checklist

| Item | State |
| --- | --- |
| `APP_KEY` set (32+ chars) | required at boot; `SignedLink` refuses to construct otherwise |
| `APP_DEBUG` off, `display_errors` off | verified in `public/index.php` |
| HTTPS + HSTS | HSTS sent when the request is HTTPS; the redirect itself is the host's job |
| Migrations applied | 21 migrations, idempotent, tested from an empty database |
| Database user limited to one schema | documented; grants were `wiz.*` only in this environment |
| Session cookie flags | `httponly`, `Lax`, `secure` in production |
| Error pages | 500 with reference, 404 branded page, 405 for wrong verbs |
| Logging | application log + activity log; no customer message bodies in the log |
| Mail | `MAIL_DRIVER=log` in this environment; SMTP path covered by `MailSettingsTest` and the send-test button |
| Backups | **not implemented in code** — must be a host cron on the database and the `uploads` directory |
| Uptime / error alerting | **not implemented** — needs an external check on `/api/v1/salon` |
| Release rollback | possible: the `deploy` branch is orphan and each push is one commit |
| Dependency audit | `npm audit` / `composer audit` not run in this sandbox (no network to the advisory services) — **run before go-live** |

---

## 7. Known gaps — not fixed in this pass

1. **No CSP for the panel's HTML.** The panel loads inline styles and scripts by design;
   a policy strict enough to help would break the email studio. The API is covered. A
   follow-up would nonce the inline blocks and then ship a real policy.
2. **Tests use whatever database `DB_NAME` points at.** A local `phpunit` run wiped the
   seeded development data in this environment (the room filled with one service
   afterwards until it was re-seeded). CI is fine — it points at a throwaway database.
   Recommended: a guard in `tests/bootstrap.php` that refuses to run when the database
   name does not contain `test`.
3. **No automated backup or monitoring** — see section 6.
4. **`npm audit` / `composer audit`** could not be run here.
5. **Load and concurrency testing** was not attempted beyond the double-booking test in
   `BookingFlowTest`. The slot claim is a single transaction against a unique index, which
   is the right shape, but it has not been hammered.
6. **Email rendering across clients** (Outlook, Gmail dark mode) was not tested; the
   email studio previews are not a substitute.
7. **Amharic type rendering on the host** was not verified — the sandbox screenshots show
   `▯` boxes where the font is missing, which is an environment artefact, not a code bug,
   but the production host must serve an Ethiopic webfont for the panel.

---

## 8. Reproducing this audit

```bash
# database
mariadb -uroot -e "CREATE DATABASE wiz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd admin && php database/migrate.php migrate
php database/seed.php "Wiz Admin" admin@wizsalon.test "Password123!"
php database/seed_catalogue.php
php database/seed_media.php

# servers
cd admin && php -S 0.0.0.0:8080 -t public public/index.php
VITE_API_BASE_URL=http://127.0.0.1:8080 npm run dev   # :5173

# gate
npm run lint && npx tsc --noEmit && npm test && npm run build
cd admin && php vendor/bin/phpstan analyse && php vendor/bin/phpunit
```
