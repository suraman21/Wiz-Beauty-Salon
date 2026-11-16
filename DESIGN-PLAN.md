# WIZ Beauty Salon — public site design plan

Written before implementation, per `skills/frontend-design`. Reviewed against the generic
defaults in that skill and revised where it drifted (notes at the end).

## Subject

WIZ Beauty Salon, Addis Ababa. Bilingual — English and አማርኛ — and booked almost entirely
from a phone. Its catalogue is not generic "spa": 14 categories dominated by hair work —
**braids, sewing, mesh, ponytail, kids' braids** — plus nails (gel, shellac, acrylic), makeup,
wax and lashes.

**Audience:** women in Addis choosing a salon on a phone, comparing options and prices, deciding
on trust before price.

**The page's primary job:** get her into the booking flow with enough confidence to finish it.

**The most characteristic thing in this salon's world:** a plait. Sectioning, thread, parting,
the pattern a braider draws on a scalp. Nobody else's site opens with that. And the second
characteristic thing: the site already carries every category name in Amharic — currently
buried at 11px as an afterthought.

## The one idea

**Amharic set at full strength, beside the English, not underneath it.** Each category becomes a
bilingual lockup — ጥፍር / Nail, ሽግሽግ / Sewing — with the Ethiopic set large enough to be
a graphic element rather than a caption. This is a real property of this business, not a
decorative choice, and no template has it.

Everything else stays quiet so that idea can carry the page.

## Colour

| Token       | Hex       | Role                                                  |
| ----------- | --------- | ----------------------------------------------------- |
| `--paper`   | `#FAF8F7` | page base — a true off-white, not cream               |
| `--ink`     | `#221A1F` | body and display type, the logo's plum-black          |
| `--plum`    | `#40263A` | dark bands, two per site, no more                     |
| `--oxblood` | `#7C2033` | the single accent: prices, active state, one CTA      |
| `--mauve`   | `#B598A8` | muted meta and the logo disc's own tint, as tint only |
| `--chalk`   | `#FFFFFF` | surfaces on top of `--plum`                           |

Lilac stops being a background. It currently floods three full-width bands; washing a page in the
brand tint is what makes a brand feel like wallpaper. It survives as the logo disc and as muted
meta text.

## Type

- **Newsreader** — display and body serif. True italics, optical sizing, literary without being
  Cormorant. Carries all headlines at one weight and one colour.
- **Archivo** — labels, prices, buttons, nav, all UI chrome. Tight, sturdy, high x-height.
- **Noto Serif Ethiopic** — every Amharic string, at both caption and display size. A
  script-specific face is a requirement, not a third style.

Scale: 1.25 ratio with a fluid `clamp()` on display sizes. Body measure capped at 64ch, mobile
line-height 1.6 for sans, 1.5 for serif display.

**Emphasis comes from scale and placement, never from switching one word to italic or a colour
mid-headline.** The current site does that on seven of seven headings.

## Layout

Asymmetric 12-column grid, 6vw gutters. Content sits left-of-centre with a marginal column for
Amharic lockups and meta — not centred, not symmetric.

```
HERO (full bleed, one image, type breaking into the margin)
┌──────────────────────────────────────────────┐
│  nav                                          │
│                                               │
│   Beauty,                    [ braid photo   │
│   braided your way.            bleeding to    │
│                                the right edge]│
│   ▸ Book an appointment                       │
│                                               │
└──────────────────────────────────────────────┘

INTRODUCTION (asymmetric, no label)
┌──────────────────────────────────────────────┐
│  ውበት          A space for your own expression │
│  (large,        of beauty. Come as you are;   │
│   margin)       leave feeling more like you.  │
│                ──────                         │
│                Full catalogue, 14 categories  │
└──────────────────────────────────────────────┘

SERVICES — an index, not a card grid
┌──────────────────────────────────────────────┐
│ HAIR                                          │
│ ፀጉር   Braids                    12 options  → │
│        Sewing                     8 options  → │
│        Ponytail                   5 options  → │
│                                               │
│ NAILS                                         │
│ ጥፍር   Manicure                  10 options  → │
└──────────────────────────────────────────────┘

GALLERY (photography-led, irregular but calm)
DARK BAND (plum, one statement, one CTA)
BOOKING BAND
FOOTER
```

Alignment: left, ragged right, everywhere. No centred blocks — the current site centres three of
six sections and that symmetry is most of why the rhythm feels flat.

## Principles

1. Amharic carries the identity. If a decision could be made for any salon, it doesn't get made.
2. Photography over chrome. Six strong images at family level beat fourteen repeated thumbnails.
3. Lists where the content is a list. A catalogue of 78 bookable options is an index, not a card grid.
4. Labels say something. "12 options" and "From ETB 450" — never `02 / OUR SERVICES`.
5. One bold move. The bilingual display lockup. Everything else is disciplined.
6. Nothing on the page that isn't true.

## Fixes this pass must land

Correctness and credibility come before taste:

- **"1 SERVICEEXPLORE"** — the option-count and the Explore link collide on every service card.
- **Repeated photography** — Nail and Pedicure share one image; Make Up and Eye Lash share
  another; seven hair categories share a single combing shot.
- **Fake urgency and fake proof** — remove any invented counters or fabricated before/after where
  both halves are the same photo.
- **Booking page** — "Find your booking with phone or email" renders twice, 40px apart.
- **Header logo** — a pale lilac disc on off-white, effectively invisible.
- **Illegible controls** — gallery filter chips and card meta text sit near 10px.
- **Amharic** — no Ethiopic web font is loaded, so it falls back to whatever the OS picks.

## Self-review against the skill's list of tells

Worked through the five named clusters before coding:

1. _Warm cream + high-contrast serif + terracotta/clay accent._ The current site is close to this:
   ivory base, Cormorant, a warm plum. **Revised:** base moved to a cooler off-white, display face
   changed, accent moved to oxblood. No clay, no terracotta.
2. _Near-black + acid green._ Not applicable.
3. _Broadsheet: hairline rules, zero radius, dense columns._ My first draft leaned on hairlines.
   **Revised:** rules appear only where they encode a row boundary in the service index. Radius
   set to 3px rather than 0, and density kept low and photography-led.
4. _The SaaS-card kit._ **Revised:** the 14-card grid is deleted entirely, replaced with an index.
   No identical rounded cards anywhere on the site.
5. _Template chrome: tracked all-caps eyebrows, `·` meta strings, `→` on every link, `WORD — fragment`
   labels, tinted near-black._ **Revised:** every `overline` is gone. Labels are sentence case with
   real content. The arrow appears only on the primary CTA, not on every link.

Also removed by design: the italic second line on every heading, `01 /` numbering that isn't a
sequence, and the fade-up-on-every-section animation.

---

## Fidelity ledger (filled in after implementation)

### What was changed, and the evidence

| #   | Point                  | Before                                                                                         | After                                                                                                                 | Status                   |
| --- | ---------------------- | ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| 1   | Headline treatment     | One formula on 7 of 7 headings: line one, then an italic, lilac line two                       | Every heading one weight, one colour; emphasis from scale and placement                                               | Fixed                    |
| 2   | Section labels         | `01 / THE WIZ FEELING` … `08 / WIZ BEAUTY SALON` — tracked all-caps, numbered as if a sequence | Deleted on marketing pages. Labels are sentence case and carry real content ("12 options")                            | Fixed                    |
| 3   | Services container     | 14 identical 6px-radius cards, same shadow, 5 repeated photographs                             | A grouped index of 14 rows in 4 families; each row expands to its options with a direct booking link                  | Fixed, verified expanded |
| 4   | Card text collision    | `1 SERVICEEXPLORE`, `10 SERVICEEXPLORE` on every card                                          | Card markup removed entirely, so the bug cannot recur                                                                 | Fixed                    |
| 5   | Repeated imagery       | Nail/Pedicure shared one photo; Make Up/Eye Lash shared another; 7 hair categories shared one  | 3 new photographs generated (braids finished, braiding, kids) + family-level thumbnails; each family reads distinctly | Fixed                    |
| 6   | Palette                | Ivory + lilac flooding three full-width bands                                                  | Off-white paper, ink type, plum for two dark bands, one soft band, oxblood as the only accent                         | Fixed                    |
| 7   | Typefaces              | Cormorant Garamond + Manrope                                                                   | Newsreader + Archivo + Noto Serif Ethiopic                                                                            | Fixed                    |
| 8   | Amharic                | 11px secondary caption, OS fallback font, glyphs clipped                                       | Display-scale lockups, Noto Serif Ethiopic loaded, line-height corrected site-wide                                    | Fixed                    |
| 9   | Booking duplication    | "Find your booking with phone or email" rendered twice, 330px apart                            | Shown once per step                                                                                                   | Fixed                    |
| 10  | Booking panel headline | `clamp(48px,5vw,80px)`, ran out of its column, second line near-invisible on lilac             | `clamp(24px,2.1vw,32px)`, fits, full contrast                                                                         | Fixed                    |
| 11  | Booking chrome         | ALL-CAPS stepper, ALL-CAPS buttons and field labels                                            | Sentence case throughout                                                                                              | Fixed                    |
| 12  | Header logo            | Pale lilac disc on off-white, effectively invisible                                            | Logo on a plum disc, plus a wordmark and Amharic line                                                                 | Fixed                    |
| 13  | Illegible controls     | Gallery filter chips and card meta near 10px                                                   | Filter buttons at 13px, min 44px touch targets                                                                        | Fixed                    |
| 14  | Horizontal overflow    | —                                                                                              | 0 overflow at 360 / 390 / 768 / 1024 / 1440 on all six pages                                                          | Verified                 |
| 15  | Nowhere to look first  | Symmetric, centred, undifferentiated                                                           | Asymmetric grid, one focal point per view, two dark bands, varied section rhythm                                      | Fixed                    |

### Deliberate deviations from the plan

- **The services index keeps a hairline between rows.** It encodes a row boundary in a
  priced list, which the skill permits, rather than decorating a section.
- **One pale band survives** (the closing CTA). It is the only tinted band on the site now,
  down from three.
- **Beauty, your way.** is kept, but demoted to the footer and stripped of its italic switch.
  It is the salon's own tagline; deleting it would have been overreach.
- **Amharic strings are transliterations and terms already in the repo.** I did not invent
  new Amharic prose — only reused names from `src/data/services.ts` plus the brand line.
- **The bulk of the legacy stylesheet is still in `styles.css`.** The marketing pages moved to
  a separate `.wz-*` namespace, and the booking flow was retuned in place rather than rewritten.
  Removing ~1,800 lines of now-inert marketing CSS is the obvious follow-up, deliberately left
  out of this pass so the diff stays reviewable and the booking flow keeps working.

### Verification actually performed

- Every page rendered and screenshotted at 1440 and 390, plus 360 / 768 / 1024 for overflow.
- Scroll, expand-a-category, open-the-lightbox, and drag-the-before/after-slider all driven
  in a real headless Chromium; booking deep links resolve to `/booking?service=<slug>`.
- `bun run test` — 70 passed. `bun run build` — succeeds, 21 pages prerendered.
- `npx tsc --noEmit` — no errors in any file this pass touched. 15 pre-existing errors remain in
  `booking.tsx`, `booking-api.ts` and `recover-manage.test.ts`; they were present at HEAD and
  are untouched by this work.
- `npx prettier --write` on the touched files; `eslint` clean on them.
- The one thing I could **not** verify: the panel API returns 502 in this sandbox, so every page
  was checked against the bundled fallback catalogue. Live data paths (prices, panel photos,
  contact details) are exercised only by the unit tests.
