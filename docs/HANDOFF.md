# Handoff

What someone taking this project over needs to know that is not in the code: where the accounts are, what is not finished, what was decided and why, and what the salon has not yet been asked.

Written to be read once, in about twenty minutes, then used as a checklist.

---

## In one paragraph

A bilingual (English / አማርኛ) marketing site for a beauty salon in Addis Ababa, with an online booking request flow, a private manage link for the customer, and a staff panel to run all of it. The site is prerendered static files; the panel and the JSON API are one plain-PHP application with a MariaDB database; both are built by CI from `main` and deployed by pulling a generated `deploy` branch on the salon's cPanel host. There is no framework, no ORM, no Node process in production and no third-party service the salon depends on. Everything a customer can read — hours, prices, photos, the change window, the wording of the terms and the emails — is editable in the panel without a deploy.

---

## Where everything lives

| Thing | Where |
| --- | --- |
| Code | `github.com/suraman21/Wiz-Seauty-Salon` (branch `main`) |
| Built releases | the same repository, branch `deploy` (regenerated on every green push) |
| Site, panel, API | `https://wiz.arkeonethiopia.com`, `/admin`, `/api/v1` |
| Host | cPanel account `arkeonet` (paths are hardcoded in `admin/deploy/deploy.sh`) |
| Server tree | `~/wiz-deploy` (clone of `deploy`), `~/wiz-app` (app + `.env`), `~/wiz.arkeonethiopia.com` (docroot), `~/backups/wiz` |
| Database | `arkeonet_wiz` on the same host |
| CI | `.github/workflows/ci.yml` — four jobs, all required |
| Secrets | `~/wiz-app/.env` on the server only. Nothing in git |

**Paths that are not portable.** `deploy.sh` hardcodes the account name, the docroot, the app directory, the PHP binary and the health-check URL. On another host or subdomain the deploy still runs and fails its final checks loudly. Change them in the committed copy and push — the server's copy is replaced on every run.

---

## Accounts to hand over

| Account | Held by | Needed for |
| --- | --- | --- |
| GitHub repository (owner) | salon's developer | CI, releases, code |
| cPanel (`arkeonet`) | salon's host | deploys, database, backups, cron |
| Panel administrator | salon owner | everything in the panel |
| SMTP mailbox (`MAIL_*`) | salon | booking and confirmation emails |
| Domain / DNS / Cloudflare | salon | the site's address |

Nothing else. There is no third-party API, no analytics account, no CDN with its own configuration, no monitoring service. That was deliberate: every service you add is one more thing to lose access to.

---

## Read these, in this order

1. **`docs/ARCHITECTURE.md`** — the two runtimes, the layering, the security decisions and why each one exists.
2. **`docs/BOOKING-RULES.md`** — the rules, and the exact class or setting that owns each.
3. **`docs/OPERATIONS.md`** — deploy, verify, roll back, back up.
4. **`docs/RUNBOOK.md`** — the failure modes already thought about.
5. **`docs/DECISIONS.md`** and **`docs/adr/`** — why the shape is the shape.
6. **`docs/PRE-PRODUCTION-AUDIT.md`** — what was checked before go-live, including the checks that failed first.

---

## State of the work

**Working and in use:** the site (7 public pages + the bilingual terms page), the panel's twelve screens, the API's ten endpoints, multi-service bookings, the configurable change window obeyed by the page, the API and the emails, the Ethiopian calendar across site, panel and API, uploads with re-encoding, email design editing with revision history, the audit trail.

**Verified how:** 104 Vitest tests, 211 PHPUnit tests / 25,354 assertions, PHPStan level 6 clean, ESLint and `tsc --noEmit` clean, CI green on all four jobs, a Playwright pass at 1280 / 768 / 390 px with zero horizontal overflow, and a manual booking made and cancelled end-to-end on a phone.

**Not finished — honest list:**

| Item | Why it matters | Effort |
| --- | --- | --- |
| No off-site automated backup | a disk failure loses the bookings and every uploaded photo | an hour, documented in `docs/OPERATIONS.md` |
| No uptime monitoring | the salon hears about outages from customers | fifteen minutes |
| No CSP on the panel's HTML | one layer of defence in depth missing | a few hours: inline styles are the blocker |
| Real photography and prices | placeholders are labelled, but they are placeholders | the salon's decision |
| Manage link lives 24 hours | disclosed in the terms; `/booking/recover` issues new ones | one line if the salon wants it longer — raise it, don't just change it |
| No i18n framework | the terms page and the site's Amharic strings are hand-written pairs | fine at this size; revisit if a third language appears |

**Not verified by CI, only by hand:** SMTP delivery, cPanel deployment, browser rendering, and the panel's email designer in Safari. If you change any of those, look at them yourself.

---

## Decisions a newcomer might want to reverse

Each is defensible; none is sacred. Read the reasoning before undoing it.

1. **Plain PHP, no framework.** The host has PHP and MySQL and nothing else. A framework would add a deploy step and a dependency surface for a panel of twelve screens.
2. **Prerendered site, no SSR.** No Node in production, no runtime that can crash the marketing pages.
3. **`deploy` branch rather than push-to-server.** The server holds no deploy key.
4. **Postgres-style layering in a small app** (repositories, services, core). It costs some ceremony and buys a codebase where SQL is in one place and rules are testable without HTTP.
5. **Ethiopian calendar as the primary clock, Gregorian in brackets.** See `docs/adr/0001-ethiopian-calendar-and-clock.md` — the alternative was tried and rejected.
6. **`booking_slots` with a primary key as the double-booking guard.** The alternative — checking availability in PHP — loses under concurrency.
7. **Bun was replaced by npm** for the site's build. `package-lock.json` is committed, CI uses `npm ci`. See `CHANGELOG.md`.

---

## If you are asked to change something

- **A booking rule** → `docs/BOOKING-RULES.md` names the owner. Change it in the class, not in the template, and update the tests that assert the sentence.
- **Wording the customer reads** → the panel's Settings or Email designs, not the code, unless it is the change-window sentence (which is generated).
- **A new screen** → follow the walkthrough in `docs/DEVELOPMENT.md` § *Adding a feature, end to end*.
- **Anything touching the schema** → a new migration, reversible, and `main` must still apply cleanly from empty (CI enforces this).
- **Anything touching the deploy** → `admin/deploy/deploy.sh` in the committed copy, never the server's.

---

## The salon's own to-do list

Things the software is waiting on a human for:

- Real photographs for the gallery and the four website image slots.
- Prices, or a decision to keep "price to be confirmed".
- Reviews, or a decision to launch without the section.
- The change window they actually want (24 hours is the default, not a preference they expressed).
- Confirmation that `MAIL_SALON_EMAIL` is an address someone reads daily.

---

## Contact order when something breaks

1. `docs/RUNBOOK.md` — most of it has happened before.
2. `git log --oneline -20` on `main` — the recent commits say what changed and how it was checked.
3. `~/logs/wiz-deploy.log` and `~/wiz-app/storage/logs/` on the server.
4. The salon, before any change that a customer would notice.
