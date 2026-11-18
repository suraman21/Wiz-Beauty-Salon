# Roadmap

What is left, in the order it should be done. Everything above the line is finished and in use; everything below it is a decision someone has to make, not a bug someone has to fix.

---

## Done

- Brand and design foundation; home, services, gallery, about, contact.
- The booking flow: services → Ethiopian calendar → time → details → request.
- The catalogue and its bilingual (English / አማርኛ) labels, filters, deep links per service.
- The customer's manage page: view, move, cancel — with the change window enforced, and the reason shown when it is closed.
- Lost-link recovery by phone **or** email, rate-limited and non-enumerating.
- The panel's thirteen screens, including multi-service bookings, the schedule, the email designer, the booking terms editor and the audit trail.
- The change window as a setting, obeyed by the page, the API and the emails.
- The booking terms at `/booking/information`: 12 sections, 30 clauses, both languages, linked from every step. Prices, deposits and fees are deliberately absent — money is settled in person.
- The terms as content, not code: every heading and clause is edited in **Administration → Booking terms**, in both languages, with add/delete/reorder, and the public page and `/api/v1/terms` follow the save. The change window stays a generated phrase (`{{window}}`, `{{policy}}`) so the wording cannot drift from the booking rule.
- Ethiopian calendar and clock across site, API and panel, with the Gregorian date always beside it.

---

## Next, roughly in order

1. **Off-site backup** — the single highest-value remaining task. Cron and restore drill are written in `docs/OPERATIONS.md` § Backups; it needs an hour and a destination.
2. **Uptime monitoring** — an external check on `/api/v1/services` every five minutes, alerting the salon's address.
3. **Real content** — photographs for the gallery and the four website image slots; prices, or a deliberate decision to keep "price to be confirmed"; reviews. All of it is waiting on the salon, not on code.
4. **A Content-Security-Policy for the panel** — the last hardening item. The inline styles in the panel's own templates are the obstacle; the API already has a CSP.
5. **Decide the manage link's lifetime** — 24 hours today (disclosed in the terms, recoverable any time). If customers are expected to move bookings weeks ahead, tie the link to the appointment plus the change window instead.
6. **A second pair of eyes on the email designer in Safari**, the one browser nobody has checked it in.

---

## Considered and deliberately not done

- **A framework for the panel** — the host has PHP and MySQL; twelve screens do not pay for the dependency.
- **Server-side rendering for the site** — the marketing pages must not be able to crash, and the host cannot run Node.
- **Online payment / deposits** — the salon takes payment in person. Adding a payment provider would add an account to lose access to, a reconciliation problem, and a compliance surface. Revisit only if the salon asks.
- **SMS reminders** — the same argument, plus a per-message cost the salon has not budgeted.
- **A customer account system** — the signed link does the same job with nothing to remember and nothing to breach.
- **Analytics** — no third-party script has been added on purpose; the site fetches no third-party resource at all.
