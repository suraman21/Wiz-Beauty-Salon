# ADR-0001: The Ethiopian calendar and clock for every date the salon shows

| Status | Date | Deciders |
|---|---|---|
| Accepted | 2026-11-17 | Salon owner, maintainers |

> Ported from the FKSS Sunday School platform (`admin/backend/ethiopian_date.php`,
> `admin/js/wbws-calendar.js`, `Mobile/.../ethiopian_calendar.dart`), where the
> same decision is recorded as ADR-0008. The arithmetic is copied deliberately,
> not re-derived.

## Context

The salon is in Addis Ababa and its customers, staff, closures and holidays are
all spoken about in the Ethiopian calendar ("after Meskerem", "closed Pagume
6"). The site and panel were showing Gregorian dates and 24-hour clock times,
so every date a customer read had to be converted in their head, and a closure
announced over the phone could be written down as the wrong day.

## Decision

1. **Storage and the wire stay Gregorian.** Every date column, every
   `/api/v1/calendar` day, every booking payload and every `.ics` file is
   Gregorian `YYYY-MM-DD`, exactly as before. Date maths, sorting, indexes and
   existing consumers keep working.
2. **Everything a person reads is Ethiopian.** The Ethiopian date is derived
   from the stored Gregorian value at render time — in the browser via
   `src/lib/ethiopian-calendar.ts`, in the panel via `Wiz\Support\EthiopianDate`.
3. **Times are shown on the Ethiopian clock, with the 24-hour time in
   brackets**: `3:00 ሰዓት (09:00)`. Ethiopian 12 o'clock is 06:00.
4. **The panel's date inputs stay Gregorian.** A native `<input type="date">`
   submits `YYYY-MM-DD`; converting it would mean a second Ethiopian
   implementation in JavaScript and a picker that fights the browser. The
   Ethiopian reading is shown immediately beside the field ("Today is Hidar 8,
   2019 EC") and on every screen that displays the saved value.
5. **The API gained fields, it did not change any.** `ethiopian`, `label`,
   `slots_ethiopian`, `booking_date_ec`, `start_time_label` are additive;
   `date`, `time`, `slots` are untouched. `POST /api/v1/bookings` additionally
   *accepts* `date_ec` (or `ethiopian_date`) and cross-checks it against `date`
   when both are sent.

## Consequences

- **Positive:** dates on the site, the panel, the customer's manage page, the
  emails and the PDFs now match how the salon already talks.
- **Negative:** two implementations of the same arithmetic exist (TS and PHP).
  They are pinned to each other by identical test vectors —
  `src/lib/ethiopian-calendar.test.ts` and `admin/tests/Unit/EthiopianDateTest.php`
  — so a change on one side breaks the other side's suite.
- **Boundary:** both engines are correct through Gregorian 2099. Gregorian 2100
  is not a leap year while the Ethiopian year spanning it is, so a one-day
  drift starts there; the month-length rule must then switch to the true
  Ethiopian rule (`year % 4 === 3`). FKSS documents the same boundary.

## Evidence

- `src/lib/ethiopian-calendar.ts`, `src/lib/ethiopian-calendar.test.ts` (25 tests)
- `admin/src/Support/EthiopianDate.php`, `admin/tests/Unit/EthiopianDateTest.php` (14 tests)
- `admin/src/Support/helpers.php` — `ethiopian_date()`, `ethiopian_clock()`,
  `full_date()`, and the rest of the funnels every panel date goes through.
