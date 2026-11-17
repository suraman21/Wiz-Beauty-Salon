> **Historical document.** This was written during the build, on the date below, and is kept
> because it records what was found and why it was changed. It is **not** a description of
> current behaviour — for that, read `docs/BOOKING-RULES.md` and `docs/ARCHITECTURE.md`.

# Research Report — Manage Booking Link & Recovery
## WIZ Beauty Salon — Professional Standard Audit

**Date:** 2026-10-20  
**Scope:** Confirmation screen link visibility, `/booking/manage/{token}` UX, lost-link recovery  
**Protocols:** UNIVERSAL CHANGE v1.0 + AI Design OS v1.0  
**Current commit:** `ae59412` (post booking-dropdown fix)

---

### 1. Current Behavior (Verified)

**Confirmation (booking.tsx step 5):**
```tsx
<p className="demo-note">Keep this link to change or cancel it: <a href={confirmed.manageUrl}>manage this booking</a></p>
```
- `demo-note`: `font-size 13px, muted, max-width 46ch` — visually secondary, not actionable
- Link text generic (“manage this booking”), no reference, no copy affordance, no icon, no button
- On mobile, link wraps and is indistinguishable from body text — user reported “hidden with the text, no chance to get that link”
- `manageUrl` is signed JWT `Ym9va2luZzo1OjE3...` → `https://wiz.arkeonethiopia.com/booking/manage/TOKEN` (24h expiry, `purpose=booking`, verified via `SignedLink::verify`)
- Email also contains link as `actionUrl` with label “View or change this request” but same discoverability issue

**Manage page (admin/src/Views/public/manage.php):**
- Card shows `reference`, status badge (`Requested`/`Confirmed`), date/time, service, customer
- If `canManage` (status ∈ {Requested, Confirmed}):
  - Loop `$days` (14 days calendar from `AvailabilityService::calendar`) → per-day `<form>` with hidden `date`, radio `time`, button “Move to this time” **per day**
  - User sees 14 forms × 3-6 slots = 14 “Move” buttons — confusing: which time is selected? Must pick radio *within* a day form, then click that day’s button
  - Cancel form: single button “Cancel this appointment” with `confirm()` dialog
- No hierarchy: “Move it” and “Cancel it” both `card__title` h2, same weight
- No policy, no contact fallback, no indication that original slot is freed
- Error/success via `Session::flash` → `alert--success/error` but no inline validation
- User reported: “very confusing page that have list of day and time, didn’t understand”

**Lost link:**
- No recovery UI. Token is only credential — no phone/email lookup, no account, no “Recover booking” page
- If email not provided or link expired (24h), user must call salon. No self-service

---

### 2. How Top Salons Handle This (Research 2026)

| Platform | Confirmation | Manage Access | Lost Link / Recovery | Policy & Reminders |
|----------|--------------|---------------|----------------------|---------------------|
| **Fresha** (500M appointments/yr, marketplace + free booking page) | Date+day+time+address (tappable map)+practitioner/service+price+“what to bring”+cancellation sentence+**Add to calendar** (beats reminders). Reschedule link is **one-tap**, not buried [1] | Self-service in Fresha app: `Cancel, reschedule and rebook with ease` [5]; business can set deadline for cancel/reschedule (e.g., 24h, after need to contact) [7] | Client book via Fresha account (phone/email) — all bookings listed in app/profile. No token needed. Marketplace login via phone/email recovers all bookings. **Intelligent Waitlist** auto-fills cancellations [10] | Automated SMS/email reminders + confirmations, flexible cancellation/rescheduling deadline, deposit/prepayment option to reduce no-shows [6][10] |
| **Booksy** (mobile-first, $29.99/mo/provider) | Custom booking link, 24/7 self-booking, calendar sync | Client logs into **Booksy Customer app** → `Appointments tab → Change / Cancel` — single button for new timeframe [8][15]. Can limit reschedule window in `Business Settings → Advanced → Booking Settings` [11]; `You can limit when client can reschedule…but they can cancel anytime by logging in` [11] | Phone/email login → appointment history + rebooking rates tracked [3]. App habit loop keeps customers returning. Boost fills cancellations | Free SMS reminders, deposits, card-on-file for late cancel, Boost to fill gaps [17][18] |
| **Mindbody** (flexible, Mindbody app + branded app) | Website/Mindbody app/branded app booking, add-on services, waivers | Clients reserve via app/website, then `Book, edit, and cancel appointments` in Business app [19]; drag-and-drop calendar + recurring + waitlist [9] | Client profile in app + Google/Outlook sync → bookings recoverable without link | Automated confirmations/reminders, cancellation policy enforced via card, waitlist |
| **Vagaro / Square** (similar) | Same: date/time+address+map+price+policy+calendar | Dashboard + client login via phone/email | Phone lookup → list of appointments | SMS/email reminders, waitlist |

**Key patterns that cut no-shows (AppyPie, Salon blogs) [2][6]:**
- **Reschedule link is not a leak, it’s the point.** Reminder without reschedule link only informs; with one-tap link, no-show → released slot with hours to fill [6]
- **What has to be on confirmation:** Date+day, time, address (map), practitioner/service, price or “confirmed in person”, what to bring, **cancellation policy in one sentence**, **Add to calendar** [2]
- **Subject line:** `Your appointment on Thursday 4 Sept, 2:30pm` outperforms “Don’t forget!” [2]
- **Make cancelling easier than not turning up** [2][21]: one-tap reschedule converts would-be no-show to released slot
- **Card on file + 24h policy:** “Your appointments are very important…request 24h notice…cancellation fee 50% if <24h” [1][16][22]
- **Waitlist:** First-come confirmation when slot opens — recovers idle chair time [1]
- **Rebook before they leave:** Link in reminder email encourages rebooking [8]

**Lost link standard:**
- **No salon relies solely on a signed URL.** Industry standard is **phone/email + reference lookup** or **client account**:
  - Fresha: login with phone/email → bookings list
  - Booksy: login → Appointments tab
  - Independent salons: “Manage via phone” form — enter phone + reference → get bookings + resend link via SMS/email [21][22]
- Token URL is **supplement**, not sole access: used for email link, but fallback is `Find my booking` page
- Example flow (booksy/fresha-like): `Booking confirmation → “Lost your link?” → /booking/lookup → phone + reference or phone alone → SMS with new manage links`

---

### 3. Gaps vs Professional Standard

| Gap | Current | Standard |
|-----|---------|----------|
| **Link visibility** | Muted demo-note inline link | Prominent card: icon + truncated URL + Copy + Open + Share buttons + QR |
| **Actions** | Single link, no copy, no share | Copy, Open, WhatsApp share, Email resend, Add to calendar |
| **Policy** | Not shown on confirmation | One-sentence policy: “Free to change/cancel up to 24h before — after, please call” [1][2] |
| **Add to calendar** | None | Primary action: `.ics` download + Google Calendar link [2] |
| **Lost link** | No path | “Lost link? Enter phone” → list of active bookings + resend SMS/email |
| **Manage UX** | 14 forms, 14 Move buttons, radio per day | Single form: unified date pills + time chips + one “Reschedule to …” CTA + preview of change |
| **Status** | Label only | Badge + color (Requested amber / Confirmed green / Cancelled muted) + explainer |
| **Contact** | None on manage page | Footer: call, WhatsApp (wa.me), address map link |
| **State handling** | Flash alerts only | Loading, success with new time, error with retry, disabled when slot taken |

---

### 4. Proposed Solution (Minimal Safe Change)

**A. Confirmation (React `booking.tsx` step 5) — keep logic, replace UI:**

```
confirmation
├─ Reference + Summary (existing)
├─ Info banner: “Salon will call to confirm — not booked until we do”
├─ Manage Card (new)
│  ├─ Header: “Keep your manage link — valid 24 hours” + shield icon
│  ├─ Link row: truncated token URL + Copy (with toast) + Open
│  ├─ Share row: WhatsApp share (wa.me?text=) + Resend email (if email provided)
│  ├─ Calendar row: Add to calendar (ics) + Google Calendar
│  └─ Policy row: “Free to reschedule/cancel until 24h before • After, call salon”
├─ Lost Link Card (new)
│  └─ “Lost your link?” → enter phone to retrieve bookings [→ /booking/lookup placeholder]
└─ Actions: Book another + Back home
```

Implementation notes:
- Copy via `navigator.clipboard.writeText(manageUrl)` + `sonner.toast`
- WhatsApp: `https://wa.me/?text=${encodeURIComponent("My WIZ booking " + reference + ": " + manageUrl)}`
- ICS: generate on client `text/calendar` blob with `BEGIN:VCALENDAR... DTSTART/DTEND` from `date + time + duration` (fallback 60m), download as `WIZ-${reference}.ics`
- Google Calendar: `https://calendar.google.com/calendar/render?action=TEMPLATE&text=...&dates=...&details=...&location=...`
- Recovery link: `/booking/lookup` — new route (placeholder) that explains phone lookup will be available; for now links to `/contact` with prefill

**B. Manage page (`admin/src/Views/public/manage.php` + `PublicBookingController.php`) — keep backend, replace view:**

- Keep controller: `show()` already provides `booking`, `token`, `days`, `error/success`; only view changes
- New view:
  - Header: reference + status badge (`Wiz/Domain/Enums/BookingStatus::label()` → color)
  - Details grid (name, phone, email) + When card (date l j F Y, start–end, service)
  - If `canManage`:
    - Reschedule card:
      - Selected day/time pills (like booking date-grid) — single selection state via JS
      - Time chips (like booking time-grid)
      - Preview: “Move from Tue 22 Oct 10:00 → Thu 24 Oct 14:30”
      - Single form POST to `/booking/manage/{token}/reschedule` with hidden `date`+`time`
      - Handles: no slots → “No other times free in next 14 days — call salon” + phone link
    - Cancel card (separate, muted, with `confirm()`):
      - Warning: “The time goes back to salon — you can rebook anytime”
      - Button `Cancel appointment`
      - Policy note
  - Footer: “Need help? Call … WhatsApp … Visit us …”
  - Else: status explainer + “Book another” CTA

**C. Lost-link recovery (new):**

- **Phase 1 (now):** Confirmation + Manage both show `Lost link? Contact salon` with phone/WhatsApp/email + `Recover via phone` placeholder page (`/booking/recover` → form `phone` + `reference?` → calls new API `GET /api/v1/bookings/lookup?phone=...`)
- **Phase 2 (next):** Backend `BookingRepository::findByPhone()` + rate limiting + SMS resend (via `BookingNotifier`). Out of scope for this patch but UI prepared (card with phone input, disabled “Send link” button, demo note “This will be available soon — for now please call”)

**Security:**
- No new auth: signed link remains credential (86,400s). Recovery via phone would need rate limit (`20261019000014_create_rate_limits` already exists) + OTP or reference check — not implemented now to avoid weakening security. Current recovery is contact-only.

**Edge cases:**
- `manageUrl === null` → show “Link unavailable — check email/SMS or call salon”
- Expired/invalid token → existing `PublicBookingController::booking()` 404 with friendly message (already in `errors/public.php` with extra hint for `/booking/manage/`)
- Slot taken on reschedule → flash error `That time could not be kept.` + reload calendar (already handled via `BookingService::reschedule` result check)
- Double submit → disable button on submit via `onsubmit="this.querySelector('button').disabled=true"`

---

### 5. References

[1] Mindbody — Booking and Scheduling Software for Salons  
[2] AppyPie — Booking App UX: Patterns That Cut Drop-Offs and No-Shows (Add to calendar, reschedule link is point)  
[3] Fresha Best Salon Software 2026 Comparison (Fresha vs Booksy vs Mindbody)  
[5] Fresha Help — Reschedule appointments (drag-drop vs appointment view)  
[6] Fresha Features — Scheduling (500M appointments, 75% fewer cancellations)  
[7] Fresha — Manage online bookings settings (cancellation deadline)  
[8] Booksy — Online Booking (reschedule within notice period)  
[10] Fresha — Intelligent Waitlist auto-fills cancellations  
[11] Booksy Support — Can clients cancel/reschedule anytime? (Business Settings)  
[15] Booksy Online Booking — empower clients to manage own appointments  
[17] Booksy — Secure income (prepayments, no-show protection)  
[21] Joel C Ma — Salon Booking Best Practices 2026 (reschedule frictionless)  
[22] Salon Femia — Booking & Cancellation (24h policy, card on file)

Full URLs in `web_search` logs above.

---

### 6. Acceptance for This Patch

- [ ] Confirmation shows prominent manage card with Copy/Open/WhatsApp/Calendar, not hidden demo-note
- [ ] Link is copyable and shareable; toast confirms
- [ ] Policy sentence visible
- [ ] Lost-link hint visible
- [ ] Manage page unified reschedule (single date picker + time chips + one CTA) replaces 14 forms
- [ ] Manage page shows status badge, contact fallback, policy, proper success/error handling
- [ ] Existing `booking/manage/{token}` routes unchanged, no DB migration
- [ ] Tests still pass (62)
