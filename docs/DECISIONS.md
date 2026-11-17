# Decisions

A short log of choices that shape the codebase but are not big enough for their own ADR. Newest last. An ADR exists for anything that had real alternatives: see `docs/adr/`.

| # | Decision | Why | Reversible? |
| --- | --- | --- | --- |
| 1 | Prerender the site to static files | the host serves static files; no Node process can crash the marketing pages | yes, at some cost |
| 2 | Plain PHP for the panel, no framework | the host offers PHP and MySQL only; twelve screens do not need a framework's surface | yes |
| 3 | All SQL in `src/Repositories/` | one place to read the queries, one place to index for | easily |
| 4 | `booking_slots` with `PRIMARY KEY (slot_start)` | double-booking is prevented by the database, not by a PHP check that can lose a race | no — core |
| 5 | Manage links are signed, not stored | no per-booking secret to keep, nothing to leak, editing a character breaks it | yes |
| 6 | The change window is one setting (`ChangePolicy`) | the sentence was in three templates and contradicted the rule; now they are the same object | no — core |
| 7 | Ethiopian calendar primary, Gregorian in brackets | it is the salon's clock; customers read dates, not ISO strings. See ADR 0001 | no |
| 8 | Publishes a `deploy` branch; server pulls it | the server needs no deploy key, and what is live is inspectable | yes |
| 9 | `vendor/` ships in the release, not in git | the host has no Composer; the release must run as uploaded | yes |
| 10 | Uploads stored outside the docroot | a file inside the webroot is a file someone can execute | no |
| 11 | Validation runs on the server even when the client validates | the client is a suggestion; the server is the rule | no |
| 12 | API Ethiopian fields are *additive* | old clients keep working; the Gregorian shapes never changed | no |
| 13 | Placeholders are labelled, never disguised | a fake testimonial is a lie with a typeface | no |
| 14 | npm (with a committed lockfile) replaces Bun | two lockfiles drift; `npm ci` is reproducible and npm ships with Node. Bun's 24-hour supply-chain guard is replaced by Dependabot plus the CI audit job | yes |
| 15 | A static preview server (`scripts/serve-build.mjs`) instead of `vite preview` | the build is prerendered HTML with no server entry; `vite preview` looks for one and cannot serve it | trivially |
| 16 | `bin/preflight.php` checks deployment readiness | the failures that hurt (missing extension, unrunnable migration, unwritable uploads) are silent until a customer hits them | trivially |

---

## Format for a new entry

One row: the decision, one sentence of why, and whether it can be undone cheaply. If the "why" needs a paragraph, it needs an ADR in `docs/adr/` instead.
