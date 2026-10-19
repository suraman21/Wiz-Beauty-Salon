<!-- GUIDANCE:BEGIN -->
> [!IMPORTANT]
> Never rewrite published git history — no force-pushing, and no rebasing, amending or
> squashing commits that are already pushed. History rewriting invalidates every clone,
> breaks any editor or CI integration connected to this branch, and risks losing project
> history that cannot be recovered from this side.
>
> Keep `main` in a working state at all times: every pushed commit must install, build
> and pass the test suite.
<!-- GUIDANCE:END -->

## Project conventions

- Keep the WIZ Home page as a standalone route with section anchors until other pages are
  approved, so navigation never points to unfinished pages.
- Keep illustrative service and gallery content in local arrays and label it as demo, so
  actual salon material can replace it without implying fabricated results.
- Keep the confirmed Services/Booking catalog and booking validation in a shared local
  module, separate from unrelated editorial demo data, so both forms stay consistent without
  changing Home or Gallery.
- Every commit is authored and committed by the repository owner. Do not add co-author
  trailers or tooling attribution to commit messages, and do not name branches or tags after
  tools, bots or assistants.
