## What and why

<!-- What was wrong or missing, and what this changes. One paragraph. -->

## How it was checked

<!--
Tick what you actually ran. Do not tick what you did not run — CI will run it,
and a claim that turns out to be unverified is worse than an empty box.
-->

- [ ] `npm run lint`
- [ ] `npm run typecheck`
- [ ] `npm run test`
- [ ] `npm run build`
- [ ] `cd admin && composer lint && composer analyse && composer unit`
- [ ] By hand, in a browser (say what you did)

## Affected surface

- [ ] Public site
- [ ] Booking flow / manage link
- [ ] API shape — **additive only**; existing fields unchanged
- [ ] Admin panel
- [ ] Database migration (reversible? tested from an empty database?)
- [ ] Deploy / CI / infrastructure
- [ ] Documentation only

## Checklist

- [ ] The rule I changed is still defined in exactly one place.
- [ ] Any behaviour a customer or staff member can see is documented (`docs/…` + `CHANGELOG.md` under *Unreleased*).
- [ ] No secret, key or `.env` in the diff.
- [ ] No published history rewritten (no force-push, no rebase of `main`).
- [ ] Commits authored by the repository owner, no co-author trailers.

## Screenshots

<!-- Before/after, if the change is visual. Mobile width matters more than desktop. -->
