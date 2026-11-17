# Pre-production audit — WIZ Beauty Salon

**Date:** 17 November 2026
**Commit under test:** `47e1c36` (CI run 44137627658 — Site, Admin, Security and Publish all green; deploy branch published as `2207a82`).
The sections below were first verified at `acd9e1d` (CI 44117675853, deploy `32b4d90`); 2.7 and 2.8 were added and re-verified at the commit above.
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

### 2.6 The other pages now open on the home page's photograph — **Medium** — fixed

**Found:** each inner page's hero used a different photograph, so the site felt like five
different sites: About and Gallery showed a portrait card beside the headline, Services
had its own composition with a second model, Contact had a third.

**Fixed:** those heroes are **gone**, not decorated. Every inner page now opens with one
hero: the home page's photograph, inset inside the page measure and shorter than the home
hero (`src/lib/hero-art.ts` is the single import, so the two cannot drift apart), with
that page's own words — overline, headline, lede, and a call to action on Services and
Contact — set **on** the picture, bottom-left, where the artwork is the salon's own
purple.

The copy plays in on the home hero's terms: the headline settles from a slight
over-scale (`wiz-zoom`), the eyebrow drops in and the lede and button rise behind it
(`wiz-rise`), staggered so the hero resolves in about 1.6 s. `prefers-reduced-motion`
turns all of it off.

**The crop was the risky part.** The photograph is 1672×941 with the tiara in the top 3 %
and the face ending around 32 %. A wide, short band covered with a 16:9 picture loses
height, never width, and the frame is pinned to `object-position: 50% 0%`, so the tiara,
the face, the flowers and the top of the gown are all inside the hero at every width
tested (1280 band 1192×486, 390 band 346×565).

**On a phone** the words cannot sit on her face and stay readable, so the hero becomes a
stack at 820 px and below — the top of the picture, then the copy on the salon's own void
colour underneath — which is exactly what the home hero already does at that width.

**Verified:** all four pages prerender with `hero-band-copy` and zero `page-hero-image`
(the old hero markup), no console errors, no horizontal overflow.

### 2.7 Each page its own cut of the photograph — **Medium** — fixed

**Found:** with the same picture behind every inner page, moving from Services to About
read as "the same frame with new words". Only the copy animated; the photograph did not.

**Fixed:** one photograph, four different *cuts* of it, and the picture now moves on the
same clock as the words.

| Page | Focus | Settles from → to | Drift | Period | Wash |
| --- | --- | --- | --- | --- | --- |
| About | `52% 16%` | 1.18 → 1.04 | −1.6 % | 30 s | pink, .16 |
| Gallery | `70% 12%` | 1.02 → **1.20** (moves *in*) | +1.8 % | 24 s | plum, .18 |
| Services | `74% 6%` | 1.22 → 1.06 | −2.2 % | 34 s | plum, .16 |
| Contact | `30% 22%` | 1.24 → 1.08 | +2.2 % | 28 s | lilac `.rgba(196,120,255,.18)` |

`PageHero` takes a `variant` and stamps `data-hero` on the section; four CSS blocks set
those variables. The photograph runs `hero-settle` for 2.4 s and then `hero-drift`,
alternating, for as long as the page is open. The text rides the same beat: eyebrow at
0.18 s, headline `wiz-zoom` at 0.26 s, lede at 0.72 s, button at 0.9 s. Gallery is the
loudest of the four — it pushes *into* the frame — and Contact is the softest, further
washed to lilac, so the two pages at either end of the menu do not read alike.

`prefers-reduced-motion` holds a still frame at each page's own scale. On a phone the band
is a stack and only the settle plays, because the drift over a 300 px strip is noise.

**The hero photograph is exempt from the shared image animations** (`is-loading`,
`is-loaded` below): one element can only carry one `animation` declaration, and the hero's
settle-and-drift is the one that matters. Its loading shimmer is drawn on the band behind
it instead, through `:has`.

**Verified** at 1280 and 390: four distinct cuts, four different animation periods
(`30 s / 24 s / 34 s / 28 s` read back from `getComputedStyle`), no console errors. See
`qa/pre-production/hero-*.png`.

### 2.8 A slow connection: the words arrive first, the pictures keep trying — **High** — fixed

**Found:** on a poor connection the page was text-invisible for as long as it took the
app to hydrate — the reveal animations hold their sections at `opacity: 0` until an
observer turns them on, and that observer lives in the bundle. Late bundle, blank page.
Images that failed showed the browser's broken-file glyph, which reads as "this site is
broken" more loudly than an empty box does.

**Fixed, in three parts:**

*The words no longer depend on the bundle.* `public/boot.js` is loaded with `async` in the
head — `async` on purpose, because a classic script (inline or not) waits for the
stylesheet ahead of it, and on a slow connection the stylesheet is exactly what is late.
It marks the document `js-reveal` immediately and, if the app has not reported itself
alive (`window.__wizReady`, called from `RootComponent`) after 2.6 s, drops the marker and
sets `reveal-off`. One rule set at the end of `styles.css` then shows every waiting
element, unanimated: plain, but readable. If the app does turn up later it does not take
the words away again. `<noscript>` covers JavaScript switched off entirely.

*Every picture retries by itself.* `SmartImage` (`src/components/site/SmartImage.tsx`)
keeps the bare `<img>` — so all the existing `img` rules still apply — and:
paints a soft woven shimmer while the first attempt is in flight; **the moment an attempt
fails it swaps in a drawn stand-in** (an inline SVG with the picture's initials) so the
broken glyph has nothing left to break; then retries three times at 0.9 s / 2.2 s / 4.5 s
against a cache-busted URL (`?retry=N`), each attempt made with an off-screen `Image` so
the box never flashes; drops the picture back in with a 0.42 s fade as soon as one
succeeds, however late that is; and after the last attempt settles quietly on the stand-in
(`is-missing`), no error icon, no alt-text rubble. All 16 image call sites use it.

The stand-in breathes very slightly while attempts remain (`is-retrying`) and stops when
they are spent. Reduced motion turns the breathing off.

*Both are measured, not assumed.*

| Test | Result |
| --- | --- |
| App bundle blocked, `boot.js` allowed | class → `reveal-off`; hero headline and story copy at `opacity: 1` |
| All image requests aborted | 11 images → 40 requests (1 + 3 retries each), **0** broken images, final state `is-missing` |
| Hero photograph fails twice, then arrives | `?retry=1` at ~1.2 s, `?retry=2` at ~3.2 s → `is-loaded`, real picture, 3 requests |
| 400 kbps / 400 ms latency, gzipped build | first paint **4.2 s**, headline readable **5.2 s** (the zoom plays over it), `reveal-off` |
| 250 kbps / 700 ms latency, gzipped build | first paint **6.7 s**, headline readable **7.7 s**, `reveal-off` |
| 1.6 Mbps / 150 ms latency, gzipped build | first paint **1.2 s**, headline readable **2.1 s**, `js-reveal` (animation intact) |
| The same build **without** compression, 400 kbps | first paint **16.4 s** |

**Two things this depends on.** The deployment must serve compressed assets: the CSS is
227 KB raw / 38 KB gzipped and the JS 549 KB raw, and that difference was the 12 s above.
GitHub Pages compresses by default; a host that does not would undo this work. And the
2.6 s fallback is a timer, not a measurement of the network — it is deliberately shorter
than a hydration on 3G takes (4 s+) and longer than a local one, which is the right side
to err on.

**Not covered:** with JavaScript off entirely the hero and the static sections show, but
client-rendered blocks (the service cards, the gallery grid) are simply not on the page —
they never were. That is recorded in section 7.

## 3. The gate

Run in this environment after the last change.

| Check | Command | Result |
| --- | --- | --- |
| TypeScript | `tsc --noEmit` | clean |
| Lint | `eslint .` | clean |
| Site unit tests | `vitest run` | 10 files, **102 tests** passed |
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
- `smart-image.test.tsx`: asks for the picture and marks it loading, fades to loaded, draws
  the stand-in on failure, retries against a cache-busted URL, puts the picture back when a
  later attempt succeeds, settles after the last attempt and stops asking, shows the
  stand-in when there is no source at all

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
6. **The reveal fallback needs `boot.js` to arrive.** If every script is blocked the hero
   text still shows but the animated sections stay hidden; `<noscript>` only helps when
   JavaScript is switched off, not when one file fails to download. A CSS-only guard would
   close it, at the cost of showing those sections unanimated to everyone.
7. **Compression is load-bearing** — see 2.8. Worth checking on the host after the first
   deploy rather than trusting the default.
8. **Email rendering across clients** (Outlook, Gmail dark mode) was not tested; the
   email studio previews are not a substitute.
9. **Amharic type rendering on the host** was not verified — the sandbox screenshots show
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

# the slow-connection probes — against the built site, served with gzip so the
# numbers mean what the host will do
npx vite build
npm i --no-save playwright-core   # the probes drive a real browser
node node_modules/playwright-core/cli.js install chromium
node scripts/low-bandwidth/serve.mjs &   # serves .output/public, gzipped, on :4173
node scripts/low-bandwidth/first-paint.mjs      # 250 kbps / 400 kbps / 1.6 Mbps
node scripts/low-bandwidth/retry.mjs            # a photograph that fails twice, then arrives
node scripts/low-bandwidth/fail-all.mjs         # every image aborted: stand-ins, no broken icons
node scripts/low-bandwidth/bundle-blocked.mjs   # the bundle blocked: the words still show
node scripts/low-bandwidth/hero-cuts.mjs        # the four hero cuts, desktop and phone
```
