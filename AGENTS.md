<!-- GUIDANCE:BEGIN -->
> [!IMPORTANT]
> Never rewrite published git history — no force-pushing, and no rebasing, amending or
> squashing commits that are already pushed. History rewriting invalidates every clone,
> breaks any editor or CI integration connected to this branch, and risks losing project
> history that cannot be recovered from this side.
>
> Keep `main` in a working state at all times: every pushed commit must install, build
> and pass the test suite.
>
> Every commit is authored and committed by the repository owner. Do not add co-author
> trailers or tooling attribution to commit messages, and do not name branches or tags after
> tools, bots or assistants.
<!-- GUIDANCE:END -->

## Project conventions

These apply to anyone — human or automated — working in this repository. The full
rationale is in `CONTRIBUTING.md` and `docs/ARCHITECTURE.md`.

### Never

- **Never commit a secret.** No `.env`, no keys, no tokens, no database dumps. If one
  reaches a commit, rotate it at the source and record it in `CHANGELOG.md`.
- **Never change an API shape.** Fields may be added; nothing may be renamed, retyped or
  removed. The site and the panel deploy at different moments.
- **Never write SQL outside `admin/src/Repositories/`.**
- **Never delete a booking's history.** Statuses change; rows stay.
- **Never claim a check you did not run.** If PHPStan was not run, say so.

### Where things belong

- **A rule lives in one place.** The change window is `Support/ChangePolicy`; slot
  arithmetic is `AvailabilityService` + `Support/EthiopianDate`; every query is a
  repository method. Editing a second place to change one behaviour is a bug, not a fix.
- **Wording a customer reads** comes from the panel or from `ChangePolicy::summary()` —
  never typed into a template next to the rule it describes.
- **Placeholders stay labelled.** Demo services, sample photos and unwritten testimonials
  say so in the interface; a plausible fake is worse than an obvious placeholder.
- **Both languages, always in pairs.** Amharic sits next to its English string in the same
  file, so a missing translation shows up in the diff.

### Before you call it done

- Site: `npm run lint && npm run typecheck && npm run test && npm run build`.
- Panel: `cd admin && composer lint && composer analyse && composer unit`.
- Behaviour a customer or staff member can see is documented in `docs/` **and** in
  `CHANGELOG.md` under *Unreleased*, in the same commit.

### Documentation map

`README.md` is the front door. `docs/README.md` is the index. Everything else is linked
from there — do not add a document that nothing links to.
