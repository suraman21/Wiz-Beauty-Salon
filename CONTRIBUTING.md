# Contributing

This is a small, private codebase with high standards for changes. Most of what follows is not bureaucracy — each rule exists because ignoring it has already caused a problem.

---

## Before you change anything

1. **Read `docs/ARCHITECTURE.md`.** Ten minutes there saves an afternoon of putting a rule in the wrong layer.
2. **Find the owner of the thing you are changing.** Booking rules live in `Support/ChangePolicy` and `Services/AvailabilityService`; SQL lives in `Repositories`; the customer's wording lives in the panel or in `ChangePolicy::summary()`. If you are editing a second place to change one behaviour, you have found a bug.
3. **Reproduce the problem, or write down the change you intend.** A commit that says "improve booking" cannot be reviewed and cannot be reverted precisely.

---

## The non-negotiables

- **`main` always installs, builds, tests and deploys.** CI enforces it: four required jobs.
- **Published history is never rewritten.** No force-push, no rebase, no amend on `main`. To undo something, revert it.
- **Never commit a secret.** `.env`, keys, tokens, database dumps. `.gitleaks.toml` and the CI secret scan are the backstop, not the plan.
- **Never break an API shape.** Add fields; do not rename or retype one. The site and the panel deploy at different moments, and an old client must keep working.
- **Every `POST` is CSRF-checked and every write is validated on the server.** Client-side validation is a courtesy.
- **No SQL outside `src/Repositories/`.**
- **A rule is enforced in one place.** If the same rule appears twice, one of them will be wrong within a month.
- **Nothing is deleted from a booking's history.** Statuses change; rows stay.

---

## Making a change

```bash
git switch -c feat/short-description     # work on a branch, not on main
# … make the change, with tests …
npm run lint && npm run typecheck && npm run test && npm run build
cd admin && composer quality             # lint, PHPStan, PHPUnit, audit
```

If you cannot run `composer quality` locally (no PHPStan binary, no database), say so in the pull request and let CI be the gate. Do not claim a gate you did not run.

Then:

```bash
git add -A
git commit      # see the message format below
git push -u origin feat/short-description
```

A pull request that merges to `main` runs the full suite; the site and the panel both have to pass before the `deploy` branch is republished.

---

## Commit messages

`type(scope): what changed`, then a body that explains the three things a reader cannot get from the diff:

```
fix(manage): a link that expires is told so, not a 404

The manage page treated an expired token as a missing booking, so a customer
whose link had aged out saw "this booking does not exist" and called the salon
convinced their appointment was gone.

Now the token's expiry is read before the lookup and the page says the link has
expired and offers /booking/recover.

Verified: PHPUnit Feature/BookingFlowTest (3 new cases), and by hand against a
token minted with a past expiry.
```

- **Types:** `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `chore`, `style`, `build`, `ci`.
- **Scopes in use:** `site`, `admin`, `booking`, `api`, `calendar`, `manager`, `deploy`, `ci`, `docs`.
- **The body says what was wrong, what changed, and how you know.** "Verified:" is not optional for a behavioural change.
- **One concern per commit.** A reformat and a bug fix in one commit cannot be reviewed or reverted separately.
- **No co-author trailers and no tool attribution.** Commits are authored by the repository owner.

---

## Tests

- **A bug fix comes with a test that failed before it.** If you cannot write one, say why in the pull request.
- **Add to the suite that already covers the area**, rather than inventing a third place to look. `docs/TESTING.md` maps the files.
- **Test behaviour, not implementation.** "Cancelling inside the window returns the reason and does not change the status" survives a refactor; "`ChangePolicy::hours()` returns 24" does not.
- **No sleeps, no real clock.** Anything date-sensitive takes "now" as a parameter.
- **PHPUnit wipes its database.** Point `DB_NAME` at a throwaway database before running it.

---

## Style

- **The site:** Prettier and ESLint decide. `npm run format` then `npm run lint`. Do not hand-format.
- **PHP:** four spaces, `declare(strict_types=1)`, typed properties and return types, PHPStan level 6 clean, and comments that explain *why* rather than *what*. `composer lint` catches syntax only.
- **CSS:** `src/styles.css` is one file on purpose. Add rules inside the page's own block, marked by a comment naming the page.
- **Comments:** if a decision looks arbitrary, the comment says what was tried and rejected. That is the kind of comment that earns its place; a comment restating the code is noise.
- **Amharic strings** are written next to their English pair, never in a separate file, so a missing translation is visible in the diff.

---

## Documentation

A change that alters behaviour visible to the salon or to a customer updates the matching document **in the same commit**:

| Changed | Update |
| --- | --- |
| a booking rule or setting | `docs/BOOKING-RULES.md` |
| an endpoint or its fields | `docs/API.md` |
| a table, column or index | `docs/DATA-MODEL.md` |
| a screen staff use | `docs/ADMIN-HANDBOOK.md` |
| deploy, backup or verification | `docs/OPERATIONS.md` |
| a decision with alternatives | `docs/adr/` (new ADR) or `docs/DECISIONS.md` |
| anything | `CHANGELOG.md` under *Unreleased* |

Stale documentation is treated as a bug, not as a chore: it is how the next person breaks something that used to work.

---

## Reviewing

A reviewer's job is to answer three questions, in order:

1. **Does it do what the message says?**
2. **Does it leave a second copy of a rule anywhere?**
3. **What happens when the thing it depends on is missing, slow, or says no?**

Anything about taste comes after those three.
