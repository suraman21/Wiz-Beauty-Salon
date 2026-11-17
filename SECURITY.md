# Security

A public booking site with a staff panel, holding customers' names, phone numbers and email addresses, and the salon's calendar. Small, but worth protecting properly.

---

## Reporting a vulnerability

**Do not open a public issue.** Email the repository owner directly, or use GitHub's private *Report a vulnerability* advisory on this repository.

Please include: what you did, what you expected, what happened, and (if you can) the request or URL involved. A short reproduction beats a long description.

**What to expect:** an acknowledgement within a few days, an assessment with a fix or a reason, and credit in `CHANGELOG.md` if you would like it. This is a single-salon project maintained alongside other work — there is no bug bounty, and no SLA. If the finding is being actively exploited, say so in the subject line.

## In scope

- `https://wiz.arkeonethiopia.com` — the site, `/admin`, `/api/v1/*`, `/booking/manage/*`, `/uploads/*`
- The code in this repository, including the deploy scripts

## Out of scope (and why)

- **Reports that require a compromised device or an already-stolen password.** Account recovery is a salon process, not a software one.
- **Missing headers that are already listed as known gaps below.**
- **Rate-limit thresholds** — they are tuned for a salon's traffic, not for a load test. If one is trivially bypassable, that *is* in scope.
- **Email content, SPF/DKIM/DMARC configuration** of the salon's mailbox — that belongs to the mail provider.
- **Automated scanner output with no demonstrated impact.**

---

## How the system is hardened

Each of these is a decision that exists because of a specific attack, not a checklist item.

### Authentication and session

| Control | Where |
| --- | --- |
| `password_hash()` with the default algorithm, rehash on login | `Core/Auth` |
| Session id regenerated on login; cookies `HttpOnly`, `Secure`, `SameSite` | `Core/Session` |
| Rate limits per email **and** per IP (`LOGIN_MAX_ATTEMPTS`, `LOGIN_MAX_ATTEMPTS_PER_IP`, `LOGIN_LOCKOUT_MINUTES`) | `Core/Auth`, `login_attempts` |
| Role gate on every admin-only route, checked in middleware rather than in each controller | `Middleware/AdminOnly` |
| Deactivating a user ends access immediately without deleting their history | panel → Team |

### Requests

| Control | Where |
| --- | --- |
| CSRF token on every state-changing form, verified by the dispatcher | `Core/Csrf`, `Middleware/Csrf` |
| API writes origin-checked and rate-limited | `Support/Origin`, controllers |
| Server-side validation regardless of client state | `Core/Validator` |
| Honeypot fields on the public forms | booking and message controllers |
| Recovery endpoint returns the same shape whether or not it found anything, five tries per five minutes | `Api/BookingApiController::recover()` |

### Data and files

| Control | Where |
| --- | --- |
| Every statement prepared with named parameters; no string-built SQL | `Repositories` |
| Uploads verified by content (`finfo` + `getimagesize`), renamed to a random hex name, and stored **outside** the docroot | `Services/Uploader` |
| Staff-authored email HTML sanitised with HTML Purifier before storage | `Services/EmailTemplateService` |
| Output escaped through `e()` in every template; raw output is commented where it is deliberate | `Views` |
| Customer manage links signed with `APP_KEY`, never stored, expiring | `Support/SignedLink` |
| Secrets only in `~/wiz-app/.env`, mode `600`, outside the docroot, never in a release | `deploy.sh`, `.gitignore` |
| `.htaccess` denies dotfiles; the app directory is not reachable over HTTP | `admin/public/.htaccess`, `deploy/` |

### Transport and headers

`Core/Kernel::harden()` adds, on every response: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and `Strict-Transport-Security` when the request is HTTPS. The `/api/` prefix additionally gets a Content-Security-Policy.

---

## Known gaps

Stated plainly rather than buried. None of them is a live incident; each has a workaround.

| Gap | Risk | Workaround / next step |
| --- | --- | --- |
| **No CSP on the panel's HTML** | if an XSS were ever found in the panel, CSP would not contain it | the panel's inline styles are the obstacle; `Kernel::harden()` is where the header would go. `docs/ROADMAP.md` item 4 |
| **No automated off-site backup** | disk loss loses bookings and photos — an availability risk with a data-loss tail | manual cron in `docs/OPERATIONS.md` § Backups. **Do this first** |
| **No uptime monitoring** | outages are discovered by customers | external check on `/api/v1/services` |
| **`/uploads` is served without a cache policy** | not a security issue, a bandwidth one | documented in `docs/RUNBOOK.md` |
| **Manage links expire after 24 hours** | a customer who books far ahead needs a new link | `/booking/recover`; disclosed in the terms |
| **No 2FA on the panel** | a stolen panel password is enough to sign in | strong passwords, rate limits, and the audit trail; consider TOTP before the team grows |

---

## If a secret leaks

1. **Rotate at the source first** — database password, SMTP password, or GitHub token. Rotation makes the leaked value useless; deleting a commit does not.
2. Remove it from the deployment: `~/wiz-app/.env`, then `php ~/wiz-app/bin/preflight.php`.
3. If it reached git: it is in history, and history is not rewritten. Rotate, then note the incident in `CHANGELOG.md`.
4. If **`APP_KEY`** is the one: every customer manage link in every inbox stops working. Tell the salon before rotating, and expect a few phone calls.
5. Check the **Activity** screen and `storage/logs/` for use of the leaked credential.

## Supported versions

Only `main` is supported. Deployments follow `main` automatically; there are no maintained release branches.
