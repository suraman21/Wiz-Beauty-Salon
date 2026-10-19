# WIZ Beauty Salon

Marketing site for WIZ Beauty Salon — a bilingual (English / አማርኛ) salon experience with a
service catalog, gallery, and a guided booking flow.

## Stack

- **TanStack Start** (SSR) + **TanStack Router** (file-based routes)
- **React 19** + **TypeScript**
- **Tailwind CSS 4** with a custom design system in `src/styles.css`
- **Vite 8** / **Vitest** / **Bun**

## Development

Requires Node.js 20+ and [Bun](https://bun.sh).

```sh
bun install
bun run dev      # dev server on http://localhost:8080
bun run build    # production build (Nitro)
bun run preview  # preview the production build
```

Quality gates:

```sh
bun run lint     # ESLint + Prettier
bun run format   # apply formatting
bun run test     # Vitest (29 tests)
bunx tsc --noEmit
```

## Project structure

```
src/
  routes/          # one file per page: index, services, about, gallery, booking, contact
  components/
    site/          # SiteChrome: shared header, footer and layout
    ui/            # shadcn/ui primitives
  data/
    wiz.ts         # navigation, gallery, testimonials, contact, demo time slots
    services.ts    # confirmed bilingual service catalog + booking validation (shared)
  lib/             # error capture, error page, utilities
  styles.css       # design system + page styles
```

`src/data/services.ts` is the single source of truth for the service catalog: 14 categories,
78 bookable options, Amharic labels, an "undecided" option, and the Zod schema used to
validate booking details. Both the Services page and the Booking flow read from it.

## Content status

The catalog structure is confirmed. Customers' names, prices, photos, testimonials, contact
details and opening hours are still placeholders and are labelled as such in the UI. The
booking flow currently completes client-side only — no request is sent or stored yet.

## Deployment

The production build targets a Nitro server output. See `WIZ-DEPLOYMENT.md` in the parent
workspace for the hosting plan and access notes.
