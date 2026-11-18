# Data model

Nineteen tables, all InnoDB, `utf8mb4_unicode_ci` set explicitly on every table (the server default on the salon's host is `latin1`, so nothing is inherited). The schema is created and changed only by migrations in `admin/database/migrations/`; there is no ORM and no model classes — repositories read rows into plain arrays.

```
service_categories ──┬── services            site_images        settings
                     ├── gallery_items ─┐    business_hours     closures
                     └── bookings       │    reviews            contact_messages
                          │             └─── gallery_items.category_id
                          ├── booking_slots            (one row per occupied half-hour)
                          └── booking_status_history

users ── activity_log      login_attempts      rate_limits      migrations
email_template_active ── email_template_revisions
```

---

## The tables that matter

### `service_categories` — what the salon sells

The bookable unit. The panel calls these "Services"; the API calls them services; the table name is the only place the word "category" survives.

| Column | Notes |
| --- | --- |
| `id` | given to `/api/v1/availability?service_id=` |
| `name`, `amharic` | both languages, both printed on the site |
| `slug` | URL- and filter-safe |
| `description`, `image_path` | shown on the site's services page |
| `show_on_home` | whether the home page features it |
| `sort_order`, `is_active` | display order; inactive disappears from the API entirely |

### `services` — the optional price list

A finer-grained catalogue row (a specific cut, a specific treatment) with `price_cents` and `duration_minutes`. It exists and is migrated, but the site books **categories**: what happens inside one is agreed at the salon, so there is no price list to publish. `bookings.service_id` points here when a booking was made against a specific service, and is `NULL` otherwise.

### `bookings` — one request, one appointment

| Column | Notes |
| --- | --- |
| `reference` | `WZ` + `ymd` + `-` + four characters; unique, read down the phone |
| `customer_name`, `customer_email`, `customer_phone` | email is optional — many customers only give a phone |
| `service_id`, `service_ids` | `service_ids` is the multi-service list (JSON text); `service_id` is the single-service case and the foreign key |
| `service_name` | a copy of the name at booking time, so a renamed service does not rewrite history |
| `price_cents`, `duration_minutes` | as agreed at the moment of booking |
| `is_undecided` | "I don't know what I want yet" |
| `booking_date`, `start_time`, `end_time` | Gregorian date, local times |
| `status` | `requested` → `confirmed` → `completed`, or `cancelled` / `declined` / `no_show` |
| `notes`, `admin_notes` | the customer's words, and the salon's |

There is **no token column**. The customer's manage link is a signed value (`Support/SignedLink`: reference + expiry, HMAC with `APP_KEY`), so it can be verified without a lookup, cannot be guessed, and expires even if nobody cancels anything. Rotating `APP_KEY` invalidates every outstanding link — which is exactly why `deploy.sh` generates it once and never again.

### `booking_slots` — the double-booking guard

`PRIMARY KEY (slot_start)`, `booking_id` → `bookings.id`. One row per half-hour a booking occupies. Two customers submitting the same 09:30 slot race on a primary key, and the database — not a check-then-insert in PHP — decides who wins. Slots are released when a booking is cancelled or declined.

This is the single most important design decision in the schema: an availability system that only *checks* is an availability system that double-books under load.

### `booking_status_history`

Every status change with `from_status`, `to_status`, `changed_by` (`NULL` when the customer did it through their link) and an optional note. The panel's booking screen shows this timeline; the Activity screen shows it across all bookings.

### `business_hours` and `closures`

`business_hours` is seven rows, `day_of_week` 1–7, with `opens_at`/`closes_at` and `is_closed`. `closures` is date ranges with an optional reason that is printed to the customer ("Closed: Holiday").

### `site_images`

One row per picture slot the design uses (`hero`, `about`, `before`, `after`, the four gallery bands…). `slot` is unique; `image_path` is `NULL` until the salon uploads a picture, and the site then keeps its bundled one. `Support/MediaUsage` counts references before a file is deleted, so replacing a picture cannot orphan or delete one still in use.

### `settings`

Key/value. Read at runtime on both sides of the system:

| Key | Default | Set from |
| --- | --- | --- |
| `booking.slot_minutes` | 30 | Settings → Booking rules |
| `booking.lead_minutes` | 120 | Settings → Booking rules |
| `booking.max_advance_days` | 60 | Settings → Booking rules |
| `booking.change_window_hours` | 24 | Settings → Booking rules |
| `contact.*` | — | Settings → Salon details |

Nothing else in the code reads `settings`; a key that is absent falls back to the default in the class that owns it, never to `null`.

### `users`, `login_attempts`, `rate_limits`, `activity_log`

- `users` — `role` is `admin` or `staff`; `is_active` is a soft switch, and deactivating is how someone leaves.
- `login_attempts` — per email and per IP, with an expiry, backing the panel's lockout.
- `rate_limits` — generic counters for the API (`recover:<ip>`, `bookings:<ip>`, …).
- `activity_log` — `action`, `entity_type`, `entity_id`, `meta` (JSON), `ip_address`. Append-only by convention; pruned by `bin/housekeeping.php` after `AUDIT_RETENTION_DAYS`.

### `email_template_active` / `email_template_revisions`

The active design per email kind (confirmation, cancellation, reschedule, salon notification) plus every revision ever published, so a bad edit can be compared and rolled back. Bodies are sanitised by HTML Purifier before they are stored.

### `terms_sections` / `terms_clauses` — the booking terms page

The document a customer agrees to, edited in **Administration → Booking terms**.

- `terms_sections` — `slug` (the page anchor, generated once from the English heading and never edited, so `#window` keeps working after a rewording), `title_en`, `title_am`, `sort_order`.
- `terms_clauses` — `section_id` (`ON DELETE CASCADE`), `sort_order` (inside its own section), `body_en`, `body_am`.

Both languages are `NOT NULL` and both are required by the panel: a clause written in one language is a gap for half the readers, so it is refused rather than stored. The text may contain three placeholders — `{{window}}`, `{{window_am}}`, `{{policy}}` — which `Services/TermsService` fills from `settings.booking.change_window_hours` when the document is served. That is deliberate: the terms cannot promise a change window the booking form would refuse.

The migration seeds the 12 sections and 30 clauses the site shipped with, so the panel opens on the document customers already read. `settings.terms.updated_on` holds the date printed under the page.

### `gallery_items`, `reviews`, `contact_messages`, `migrations`

Straightforward. Worth knowing: `reviews.is_approved` is what the API filters on, and `migrations` records applied filenames — `php database/migrate.php status` compares them to the files on disk.

---

## Conventions that keep this maintainable

1. **Every migration is reversible.** `up()` and `down()`; `php database/migrate.php rollback` is the escape hatch.
2. **Migrations never delete data that a deploy could still need.** Renames copy, then a later migration drops.
3. **`utf8mb4_unicode_ci` is stated per table**, because the host's default is not.
4. **`DATETIME` is stored in the salon's timezone** (`Africa/Addis_Ababa`), and the connection's session timezone is set from `APP_TIMEZONE` — otherwise `NOW()` and `CURDATE()` disagree with the salon's clock for part of every day.
5. **No SQL outside `src/Repositories/`.** Grep for `SELECT` in `src/Controllers` and `src/Views` and you should find none.
6. **Named placeholders always.** Every statement is prepared; the one exception is a `LIKE` pattern built from a validated value.

---

## Backups and recovery

`bin/housekeeping.php` prunes; it does not back up. The backup routine is `docs/OPERATIONS.md` § Backups — database dump plus the uploads directory, because a database without its files is a site full of missing pictures. Restoring one without the other is the failure mode to avoid.
