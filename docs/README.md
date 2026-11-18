# Documentation

Everything about this project that is not in the code, in the order you are likely to need it.

The top-level [README](../README.md) is the front door and the quick start. This file is the index behind it.

---

## Start here

| Document | Read it when | Time |
| --- | --- | --- |
| [../README.md](../README.md) | first contact with the repository | 5 min |
| [ARCHITECTURE.md](ARCHITECTURE.md) | you need to know how the pieces fit and why | 15 min |
| [HANDOFF.md](HANDOFF.md) | you are taking the project over | 20 min |
| [DECISIONS.md](DECISIONS.md) + [adr/](adr/) | you are about to change something structural | 10 min |

## Working on it

| Document | Contents |
| --- | --- |
| [DEVELOPMENT.md](DEVELOPMENT.md) | install, run both servers, day-to-day commands, how the code is organised, gotchas |
| [TESTING.md](TESTING.md) | both suites, what they pin down, what is *not* covered, how CI runs them |
| [API.md](API.md) | the eleven endpoints, request and response shapes, error behaviour, rate limits |
| [DATA-MODEL.md](DATA-MODEL.md) | every table, the double-booking guard, the settings keys, conventions |
| [BOOKING-RULES.md](BOOKING-RULES.md) | every booking rule and the one place that owns it |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | commit style, review expectations, the rules that are not negotiable |

## Running it

| Document | Contents |
| --- | --- |
| [OPERATIONS.md](OPERATIONS.md) | releases, deploying, verifying, rolling back, backups, monitoring, maintenance, secrets |
| [HOSTING-CPANEL.md](HOSTING-CPANEL.md) | the host-specific setup: subdomain, PHP 8.4, database, `.env`, first deploy, cron, the split docroot |
| [RUNBOOK.md](RUNBOOK.md) | symptom → diagnosis → fix, plus the known operational gaps |
| [ADMIN-HANDBOOK.md](ADMIN-HANDBOOK.md) | for the salon's staff, not for developers |

## Reference material

| Document | Contents |
| --- | --- |
| [PRE-PRODUCTION-AUDIT.md](PRE-PRODUCTION-AUDIT.md) | what was audited before go-live, what was found, what was fixed, how it was verified |
| [ROADMAP.md](ROADMAP.md) | what is left, and what was deliberately left out |
| [adr/0001-ethiopian-calendar-and-clock.md](adr/0001-ethiopian-calendar-and-clock.md) | the calendar decision, with the alternative that was rejected |
| [reports/](reports/) | historical working reports, kept for provenance — clearly marked as such |

## Repository-level files

| File | Purpose |
| --- | --- |
| [../SECURITY.md](../SECURITY.md) | how to report a vulnerability, what is in scope, the hardening that exists |
| [../CHANGELOG.md](../CHANGELOG.md) | what changed, in the order it changed |
| [../AGENTS.md](../AGENTS.md) | the rules for automated contributors working in this repository |
| [../admin/README.md](../admin/README.md) | the panel's own short guide, for someone already inside `admin/` |

---

## Conventions these documents follow

- **One owner per fact.** A booking rule is documented where it is decided, and other documents link to it rather than restating it. When a rule changes, the tests that assert the sentence fail too.
- **Numbers are quoted, not approximated.** Test counts, page counts and migration counts come from a run; if a number here disagrees with the code, the code is right and this file is a bug.
- **Gaps are listed, not hidden.** Every document with a "known gaps" section is telling you what has not been built yet.
- **Historical documents are marked.** `docs/reports/` and `docs/PRE-PRODUCTION-AUDIT.md` describe the state of a particular day. They are evidence, not instructions.
