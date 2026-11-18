# Booking rules

Every rule below is decided in **one place**, and that place is named. If a rule and a sentence disagree, the rule wins and the sentence is a bug.

---

## The rules, in the order a customer meets them

### 1. What can be booked

**Hair, Nails, Make Up, Skin** — the rows in `service_categories` that are `is_active`. A customer may book **several services at once** (they are laid end to end) or say **"I'm not sure yet"** (`is_undecided`), in which case a default length is used and the salon agrees the details by phone.

*Set in:* panel → **Services** (`/admin/categories`). *Enforced in:* `AvailabilityService::resolveDuration()`, `BookingApiController`.

### 2. When the salon is open

Seven rows in `business_hours`, each with opening and closing time or `is_closed`. Days outside them are not offered, and the page says which day it is rather than showing an empty grid.

*Set in:* panel → **Opening hours** (`/admin/schedule`).

### 3. Closures

Date ranges with a reason ("Holiday", "Wedding party"). A closure makes those days unbookable and prints `Closed: <reason>` to the customer.

*Set in:* panel → **Opening hours → Closures**.

### 4. How far ahead

`booking.max_advance_days`, default **60**. Beyond it: `Bookings open 60 days ahead.`

*Set in:* panel → **Settings → Booking rules**. *Enforced in:* `AvailabilityService::maxAdvanceDays()`.

### 5. How soon

`booking.lead_minutes`, default **120**. A slot must be at least two hours away, so nobody books a cut for ten minutes' time that the salon never sees.

*Set in:* panel → **Settings → Booking rules**. *Enforced in:* `AvailabilityService::leadMinutes()`.

### 6. Slot granularity

`booking.slot_minutes`, default **30**, floor 5. The grid the customer sees. A service longer than the grid consumes several slots.

*Set in:* panel → **Settings → Booking rules**.

### 7. No double-booking

One row per occupied half-hour in `booking_slots`, `PRIMARY KEY (slot_start)`. Two people submitting the same slot collide at the database; one wins, the other is told the time has just gone and shown a fresh grid.

*Enforced in:* `BookingRepository::reserveSlots()` — deliberately in SQL, not in a PHP `if`.

### 8. A booking is a **request**

`status = requested` until the salon confirms. The customer is told so in the confirmation screen, the email and the manage page. Only `confirmed` bookings are counted as appointments.

*Set in:* panel → **Bookings** (`Confirm`, `Decline`, `Complete`, `No-show`). Each transition is recorded in `booking_status_history` and sends the matching email.

### 9. Changing or cancelling — the change window

**`booking.change_window_hours`, default 24, range 0–336 (two weeks).**

| Value | What the customer may do | What the page says |
| --- | --- | --- |
| `24` (default) | move or cancel free, up to 24 hours before the start | *"Free to change or cancel up to 24 hours before your appointment."* |
| `48`, `72`, … | the same, with more notice | the same sentence, with the number |
| `0` | change or cancel at any time before it starts | *"You can change or cancel this appointment at any time before it starts."* |

Inside the window (i.e. later than the cut-off) the page still **shows** the appointment but replaces the buttons with the reason and the salon's phone number. It does not offer a button the server would refuse.

*Set in:* panel → **Settings → Booking rules** — presets (No cut-off, 12, 24, 48, 72 hours) or any number of hours. *Decided in:* `Support/ChangePolicy` — the only class that answers this question. *Enforced in:* `PublicBookingController` (cancel/reschedule), `BookingService`, and the API's booking creation. *Printed by:* the site (`policy.summary` from `/api/v1/salon`), the manage page, and every email that mentions changes.

**Why one class:** the sentence used to be typed into three templates, so a salon that needed 48 hours kept reading 24. Now the number, the sentence and the refusal are the same object.

### 10. The customer's link

The confirmation email carries `/booking/manage/{token}`. The token is **signed, not stored**: `Support/SignedLink` encodes `booking:<id>:<expiry>` and signs it with `APP_KEY`. Editing one character breaks it; nothing in it is secret.

**The link is valid for 24 hours** (`BookingNotifier` line ~310, and the API issues the same TTL). After that the page 404s, and the customer gets a fresh link from **`/booking/recover`** with the phone number or email they booked with — five attempts per five minutes, and the same answer whether or not a booking exists.

> **Worth knowing, and disclosed.** The 24-hour link life is shorter than the two-week maximum change window, so a customer who books far ahead will need a fresh link from `/booking/recover`. The terms say so in as many words ("expires 24 hours after it is sent… you can always ask for a fresh link"), which is why this is a documented behaviour rather than a defect. If the salon would rather the link lived until the appointment plus the window, that is a one-line TTL change in `BookingNotifier` — raise it as a decision, not a silent edit.

### 11. Cancelling the unreachable

A booking whose customer never confirms the email still stands as `requested` until the salon acts. The panel shows pending bookings on the dashboard and in the sidebar count; `bin/housekeeping.php` does not auto-cancel anything, because a salon that phones people back wants to see those rows.

---

## Who may do what

| Action | Customer (manage link) | Staff | Admin |
| --- | --- | --- | --- |
| Create a booking | ✔ public API | ✔ panel → Bookings → New | ✔ |
| Confirm / decline / complete / no-show | — | ✔ | ✔ |
| Move or cancel within the window | ✔ | ✔ (any time) | ✔ |
| Move or cancel inside the window | — refused with the reason | ✔ | ✔ |
| See customer notes / add admin notes | — | ✔ | ✔ |
| Change hours, closures, settings, booking rules | — | — | ✔ |
| Change the change window | — | — | ✔ |

Staff bypass the change window on purpose: the rule protects the salon's calendar from late customer changes, not staff from their own decisions.

---

## Where each setting lives

| Setting | Key | Default | Screen |
| --- | --- | --- | --- |
| Change window | `booking.change_window_hours` | 24 | Settings → Booking rules |
| How soon | `booking.lead_minutes` | 120 | Settings → Booking rules |
| How far ahead | `booking.max_advance_days` | 60 | Settings → Booking rules |
| Slot length | `booking.slot_minutes` | 30 | Settings → Booking rules |
| Opening hours | `business_hours` rows | 09:00–19:00 | Opening hours |
| Closures | `closures` rows | — | Opening hours |
| Services offered | `service_categories` | 14 seeded | Services |
| Phone, WhatsApp, address | `contact.*` | — | Settings → Salon details |

Nothing here needs a deploy. Every one of them is read at request time.

---

## What the customer sees, in order

```
/booking          pick services → pick day (Ethiopian calendar, Gregorian beside it)
                  → pick time → name, phone, optional email → request sent
POST /api/v1/bookings → reference + manage link
                  confirmation screen: "requested, the salon will confirm"
email             "we have your request" (+ manage link, + the change sentence)
/booking/information   the terms, in English and Amharic, linked at every step
/booking/manage/{token}  status, what was booked, move / cancel (or why not)
/booking/recover  "I lost my link" → new link by phone or email
```

The terms page (`/booking/information`, 12 sections and 30 clauses in English and Amharic, with no pricing, deposits or fees in it at all) states the change window in the clause that opens its "Changing or cancelling" section. That clause is **generated** from `policy.summary` / `hours_label`, not typed, so the terms cannot promise a window the server will refuse.
