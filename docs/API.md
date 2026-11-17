# The JSON API

Everything the public site reads or writes goes through these ten endpoints. They are served by the same PHP application as the panel (`api.php` routes, `src/Controllers/Api/`), are **public** (no authentication), and are the only part of the system a browser talks to directly.

Base URL in production: `https://wiz.arkeonethiopia.com/api/v1` — same origin as the site, so no CORS preflight and no API key to leak. In development, `vite.config.ts` proxies `/api` to the PHP server.

---

## Conventions

- **JSON in, JSON out.** `Content-Type: application/json`.
- **GETs are cacheable** with `Cache-Control: public, max-age=60` (content) or `300` (gallery, reviews). A staff edit becomes visible within that window, not instantly.
- **Writes** require the request's `Origin`/`Referer` to match the site (`Support/Origin`), are rate-limited per IP, and are validated server-side regardless of what the browser checked.
- **Errors** are `{"error": "message", "status": 422}` with the matching HTTP status. The message is written for the person who caused it — it is printed in the form, not swallowed.
- **Dates are Gregorian** (`YYYY-MM-DD`) and times are `HH:MM` in the salon's timezone (`Africa/Addis_Ababa`). Ethiopian readings are *additive* fields — clients that ignore them see exactly the old shape.
- **Service ids** are `service_categories.id`. That is the bookable unit: Hair, Nails, Makeup, Skin.

---

## GET `/api/v1/services`

The catalogue. Categories in display order, published only.

```json
{
  "services": [
    {
      "id": 1,
      "name": "Hair",
      "slug": "hair",
      "amharic": "ፀጉር",
      "description": "Cuts, colour, styling and treatments.",
      "show_on_home": true,
      "image_url": "https://wiz.arkeonethiopia.com/uploads/9f2c….jpg"
    }
  ]
}
```

`image_url` is `null` when the salon has not set a picture; the site then keeps its bundled one.

## GET `/api/v1/gallery`

Published photos, plus the categories that actually have photos.

```json
{
  "photos": [
    {
      "id": 12,
      "title": "Bridal braids",
      "caption": "Bridal morning, Bole",
      "url": "https://…/uploads/ab31….jpg",
      "category_id": 1,
      "category": "Hair",
      "category_slug": "hair",
      "show_on_home": false
    }
  ],
  "categories": [
    { "id": 1, "name": "Hair", "slug": "hair", "amharic": "ፀጉር", "photo_count": 7 }
  ]
}
```

## GET `/api/v1/reviews`

The twelve most recent **approved** reviews.

```json
{
  "reviews": [
    { "id": 3, "name": "Hanna", "rating": 5, "body": "…", "service": "Hair", "date": "2026-10-02" }
  ]
}
```

## GET `/api/v1/images`

Every picture slot the layout uses, keyed by the name the design knows it by. The site replaces its bundled image with whatever is here and keeps the bundle for any slot the salon has not filled.

```json
{ "images": { "hero": "https://…/uploads/1a2b….jpg", "about": null } }
```

## GET `/api/v1/salon`

Contact details, weekly hours, upcoming closures, and the booking policy sentence.

```json
{
  "contact": {
    "phone": "…", "whatsapp": "…", "email": "…",
    "instagram": "…", "tiktok": "…", "address": "…"
  },
  "hours": [
    { "day": 1, "label": "Monday", "opens_at": "09:00", "closes_at": "19:00", "is_closed": false }
  ],
  "closures": [
    { "start_date": "2026-12-25", "end_date": "2026-12-26", "reason": "Holiday" }
  ],
  "policy": {
    "change_window_hours": 24,
    "has_cut_off": true,
    "hours_label": "24 hours",
    "summary": "Move or cancel free of charge up to 24 hours before your appointment.",
    "chip": "Free changes until 24 hours before"
  }
}
```

The site prints `policy.summary` verbatim. That is the point: the promise and the rule come from one row (`booking.change_window_hours`), so they cannot disagree. When the window is `0`, `has_cut_off` is `false` and the site says changes are always free.

## GET `/api/v1/availability`

`?date=YYYY-MM-DD` plus exactly one of:

| Parameter | Use |
| --- | --- |
| `service_id=1` | one service |
| `service_ids=1,2` or `[1,2]` | several services, laid end to end |
| `is_undecided=1` | the customer has not chosen; `duration` is used |

The request may pass `duration=90` to override the computed length. Unknown ids return `404 {"error":"Unknown service."}`.

```json
{
  "date": "2026-11-20",
  "ethiopian": {
    "year": 2019, "month": 3, "day": 11,
    "iso": "2019-03-11",
    "label": "ህዳር 11, 2019",
    "label_am": "ህዳር 11 ቀን 2019 ዓ.ም.",
    "gregorian": "20 November 2026"
  },
  "slots_ethiopian": ["ከሰ 3:30", "ከሰ 4:00"],
  "service": { "id": 1, "name": "Hair", "duration_minutes": 60 },
  "open": true,
  "reason": null,
  "slots": ["09:30", "10:00"]
}
```

`open: false` carries a `reason` as a finished sentence, because the site prints it as-is instead of showing an empty grid:

| `reason` | When |
| --- | --- |
| `That date has already passed.` | the day is behind the salon's clock |
| `The salon is closed on Sundays.` | not a working day |
| `Closed: Holiday` | a closure row covers the day |
| `Bookings open 60 days ahead.` | past `booking.max_advance_days` |
| `That date is not valid.` | unparseable input |

## GET `/api/v1/calendar`

`?service_id=1&from=YYYY-MM-DD&days=14` — the same rules, one entry per day, for the month view. Accepts `service_ids`, `duration` and `is_undecided` as above.

```json
{
  "service": { "id": 1, "name": "Hair", "duration_minutes": 60 },
  "days": [
    { "date": "2026-11-20", "open": true, "reason": null, "label": "ህዳር 11",
      "ethiopian": { "year": 2019, "month": 3, "day": 11, "...": "…" },
      "slots": ["09:30"], "slots_ethiopian": ["ከሰ 3:30"] }
  ]
}
```

## POST `/api/v1/bookings`

Creates a **request**. The salon confirms it afterwards; nothing is guaranteed until they do.

```json
{
  "name": "Hanna Tesfaye",
  "email": "hanna@example.com",
  "phone": "+251911234567",
  "service_ids": [1, 3],
  "is_undecided": false,
  "date": "2026-11-20",
  "time": "09:30",
  "notes": "Bridal morning — please allow extra time."
}
```

`service_id` is still accepted for a single-service booking. `201` on success:

```json
{
  "ok": true,
  "reference": "WZ261120-K3P4",
  "status": "requested",
  "manage_url": "https://wiz.arkeonethiopia.com/booking/manage/eyJ…",
  "date": "2026-11-20",
  "time": "09:30",
  "ethiopian": { "date": { "…": "…" }, "time": "ጠዋት 3:30" },
  "message": "Thank you. The salon will confirm your appointment shortly."
}
```

Failure modes that matter:

| Status | `error` says | Meaning |
| --- | --- | --- |
| `422` | the validator's first message | a field is missing, malformed, or the slot was taken between the calendar loading and the submit |
| `429` | `Too many booking attempts. Please call the salon.` | the per-address limit for bookings was hit |
| `404` | `Unknown service.` | an unknown `service_id`/`service_ids` entry |

A slot that fills while someone is deciding is normal, not a bug: the insert collides with the slot's primary key, the customer is asked to pick another time, and the site refreshes the grid. The reference is `WZ`, the booking date (`ymd`) and four characters from an alphabet without `I`, `O`, `0` or `1` — short enough to read down a phone line.

## POST `/api/v1/bookings/recover`

"I lost my link." Takes a phone number **or** an email address (`identifier`, or any of `phone`/`email`/`customer_phone`/`customer_email` — old and new clients all work) and returns that person's upcoming requested/confirmed bookings with their manage links.

The response shape is identical whether one booking was found or none, so probing many identifiers teaches a caller nothing, and the endpoint allows five attempts per address per five minutes. A honeypot field (`website`) is accepted and rejected silently.

```json
{ "ok": true, "count": 1, "bookings": [ { "reference": "WZ261120-K3P4", "manage_url": "…", "date": "2026-11-20", "time": "09:30", "status": "confirmed", "services": ["Hair"] } ] }
```

## POST `/api/v1/messages`

The contact form. `{"name": "…", "email": "…", "phone": "…", "body": "…"}` → `201 {"ok": true, "message": "Thank you. The salon will get back to you soon."}`. The note is stored in `contact_messages` even if the email notification fails; the notification is best-effort and never turns into an error the visitor sees.

---

## Rate limits

Applied per IP against the `rate_limits` table, tuned per endpoint: reads are generous, `bookings` is moderate, `bookings/recover` and `messages` are strict — recovery is the endpoint that could otherwise be used to enumerate customers. Exceeding a limit returns `429` with a message meant for a human. The panel's login has its own counters (`LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_MINUTES`, per email *and* per IP).

## Not in the API

- **Nothing authenticated.** There is no token, no session and no write that needs one — the panel's screens are HTML forms with CSRF tokens, not API clients.
- **No DELETE.** A customer cancels through their signed link; the salon changes status in the panel. Nothing a browser can do deletes a row.
- **No pagination.** Every collection here is bounded by the design (a dozen reviews, one salon, the next two weeks). Customer lists, bookings and messages are panel-only and paginated there.
