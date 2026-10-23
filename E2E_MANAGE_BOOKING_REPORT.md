# Manage Booking — E2E Verification Report
Date: 2026-10-23  Africa/Addis_Ababa
Commit: 7664a21  fix(manage): phone+email lookup, distinct manage page, routing fix
Build: 711ms 11 pages prerendered (incl. /booking/recover as manage-page)

## What was wrong (from screenshot)
- URL /booking/recover showed the *booking* flow (“Book an appointment”, 5 steps) not a manage page → user could not find their bookings after leaving. Root cause: `src/routes/booking.recover.tsx` was defined as child of `booking.tsx` via dot-naming (`booking.recover`) but `booking.tsx` never rendered `<Outlet/>`. SSR therefore fell back to parent. Fixed by adding `useRouterState` check in `booking.tsx` to return `<Outlet/>` when pathname is /booking/recover.
- Only phone recovery existed; email not supported. And copy still said “Recover with phone” everywhere — user asked for “Find your booking through your phone no or email”.
- Phone lookup scanned only 20 global upcoming rows then filtered in PHP → missed bookings when salon has >20 upcoming. Now SQL pre-filters with `LIKE '%last9' LIMIT 100` then PHP normalize, fallback 200.

## Changes (minimal, production-ready)
### Backend
- `admin/src/Repositories/BookingRepository.php` — added `findRecoverableByIdentifier()` unified entry, `findRecoverableByEmail()`, phone path uses normalized last-9 suffix prefilter. `normalizePhone()` keeps +251→0 conversion. Email match is `LOWER(customer_email) = :email` exact.
- `admin/src/Controllers/Api/BookingApiController.php::recover()` — accepts `identifier|phone|email|customer_phone|customer_email` (any key), honeypot `website/_website`, Origin check, RateLimit `recover:<ip> 5/300`, Validator dispatches by `@` presence (email => email|max:190, else phone|max:40). Returns `bookings[]` with signed `manage_url` (24h SignedLink) same shape for empty vs found (no enumeration timing leak).
- `admin/src/Views/errors/public.php` 404 /booking/manage/ now copy “find your booking through your phone number or email” + button “Find your booking”.

### Frontend
- `src/lib/booking-api.ts` — `recoverBookings(identifier)` posts `{identifier, website:''}` to /api/v1/bookings/recover; aliases `recoverBookingsByPhone/Email` keep compat.
- `src/routes/booking.recover.tsx` — completely redesigned as *Manage Booking* page: overline Manage booking, H1 Find your booking., intro “Find your booking through your phone number or email…”, single input with Mail/Phone icon detection, placeholder “0912345678 or you@email.com”, hints, loading/error/empty (with Try another + Contact), results cards with reference/status/service/date/time + Manage booking + Copy link, confirm-banner 24h warning, privacy card. No “Recover” user text.
- `src/routes/booking.tsx` — Outlet fix, plus copy updates: `Already have a booking?` card → “Find your booking with phone or email”, footer “Find your booking with phone or email”, confirmation recover-card → “Find your booking through your phone number or email”.
- `src/components/site/SiteChrome.tsx` already had Manage booking header/mobile/footer — kept, now points to fixed prerender.
- `src/styles.css` — added `.manage-page` distinct styles (blush accent, visual copy scaling), preserved `.nav-manage/.mobile-manage-link/.booking-manage-card`.

## Production readiness checklist
- [x] Security: Origin assertSame, honeypot, RateLimit 5/300 per IP, SignedLink 24h, only `requested|confirmed` upcoming CURDATE→+60d, no cancelled/past leakage
- [x] Validation: phone regex `^\+?[0-9][0-9\s\-()]{6,19}$` plus email FILTER_VALIDATE_EMAIL, max lengths, friendly error “Or enter your email”
- [x] Privacy: empty vs found same JSON shape, rate limit message generic, no timing side-channel (single query path)
- [x] A11y: label Phone number or email, aria-label, role=status aria-live polite, focus-visible outline, keyboard operable, 2.75rem min tap targets
- [x] Responsive: booking-page grid → single column <760px, manage card flex column <640px, nav-manage hidden on mobile (shows mobile-manage-link), phone/email input stretches, results cards wrap
- [x] SEO: title Manage Booking, description with phone or email, og tags, prerendered 11 pages
- [x] Compat: old clients sending `phone` still work (candidate list), new sends `identifier`

## E2E test results (automated)
```
vitest run — 8 files 70 passed (was 62, +8 recover-manage)
  booking-api.test.ts 6 passed
  recover-manage.test.ts 8 passed:
    - sends identifier (phone) to /api/v1/bookings/recover
    - sends identifier (email) same endpoint
    - backwards compat aliases
    - surfaces validation error 422
    - rate limit 429 friendly
    - network failure salon message
    - booking step 0 copy contract
    - manage page copy contract

bun run build — ✓ 711ms 11 pages prerendered
  - /booking/recover prerender contains:
    Manage booking x4, Find your booking x1, placeholder 0912345678 x1, hero portrait
  - /booking index prerender loads (client hydration shows manage card)
```

## Manual journey (what user now sees)
1. Land on Home → header Manage booking (desktop underline, mobile drawer Manage booking) → click → /booking/recover prerendered Manage page (not booking steps).
2. Enter 0911223344 (or +251911223344 or hanna@example.com) → Find → results list with Manage booking buttons (signed, 24h) + Copy link.
3. Booking flow step 0 shows “Already have a booking? Find your booking with phone or email →” card; footer “Find your booking with phone or email”; confirmation “Find your booking through your phone number or email”.
4. Expired manage link 404 → “This manage link has expired… find your booking through phone or email → Find your booking”.

## Image handling
Hero portrait stays in `booking-visual-frame` inset (not full bleed) → normal size, object-position 50% 16%, filter saturate 0.95 — no quality loss, editorial containment as per constraint.

Next: monitor manage funnel conversion; no pending build.
