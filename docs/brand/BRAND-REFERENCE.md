# antaraNote Brand Reference

> Condensed brand guide for developers and implementers.

## Where the brand actually lives

**The platform branding settings are the source of truth**, not this file. They
are edited under super admin → branding and are what renders in production.
This document tracks them; when the two disagree, the settings win and this
document is wrong.

Three places in this repository described *different* brands at the same time,
and it cost a full rebuild of the mobile app's theme in August 2026:

| Location | What it said | Status |
|----------|--------------|--------|
| Branding settings (live) | Green, lime, navy · Nunito · lime tile with a navy "n" | **Correct** |
| This file, before Aug 2026 | Teal + amber gold · Plus Jakarta Sans / Inter · three dots | Now corrected |
| `docs/brand/logos/final-*.svg` | The teal three-dot mark | **Still stale — do not use** |
| `public/design-system.html` | Purple `#7C3AED` | **Still stale — do not use** |
| `public/icons/icon-*.png` | Purple PWA icons | **Still stale — do not use** |

If you change the branding settings, update this file in the same change.

---

## Brand Name

**antaraNote** — lowercase "antara" + title case "Note", no space.

- "antara" = Malay for "between/among"
- "Note" = meeting notes, minutes, documentation

**Never:** AntaraNote, Antaranote, ANTARANOTE, antara note, antara Note

---

## Tagline

| Language | Primary | Secondary |
|----------|---------|-----------|
| English | Between Words and Action | Where Decisions Are Documented |
| Malay | Antara Kata dan Tindakan | Di Mana Keputusan Didokumentasikan |

---

## Logo

A **lime rounded-square tile carrying a navy lowercase "n"**, with the wordmark
set "antara" regular and "Note" bold, both in navy.

- Tile: Signal Lime `#87FF51`, corner radius roughly 28% of the tile
- Glyph: Deep Navy `#01266E`, a **custom script letterform**
- Wordmark: Deep Navy `#01266E`

**The "n" cannot be typeset.** It is a drawn form and no installed face
reproduces it. Anything that needs the mark must load the artwork; an
approximation set in an italic sans is not the logo and must not ship.

### Where the artwork lives

Uploaded through the branding settings, so the file sits on the server rather
than in this repository. Fetch it rather than redrawing it.

`docs/brand/logos/final-*.svg` are the **superseded** teal three-dot mark and
`generate-logos-final.js` regenerates that old mark. Both should be replaced or
deleted; they are kept here only so nobody mistakes them for current.

**Rules**

- Clear space equal to the tile's corner radius on all sides
- Minimum tile size 24px digital
- Never recolour the tile or the glyph
- The lockup is horizontal; a stacked version needs its own artwork

---

## Color System

### Primary

| Token | Name | Hex | Notes |
|-------|------|-----|-------|
| `primary` | Brand Green | `#37AD00` | Primary actions, active states |
| `primary-deep` | Deep Green | `#2B8A00` | Small text and icons — `primary` is only ~3:1 on white |
| `primary-soft` | Soft Green | `#EFFBE7` | Selected rows, quiet emphasis |

### Secondary

| Token | Name | Hex | Notes |
|-------|------|-----|-------|
| `lime` | Signal Lime | `#87FF51` | **Surfaces only.** ~1.4:1 on white — must never carry text |
| `navy` | Deep Navy | `#01266E` | Headings, body text, the wordmark |

### Semantic

| Token | Name | Hex |
|-------|------|-----|
| `danger` | `#EF4444` |
| `success` | `#22C55E` |
| `warning` | `#F59E0B` |
| `info` | `#0284C7` |

### Neutrals

Not defined in the branding settings. These are biased a few degrees toward the
navy so greys read as part of the palette rather than as a default.

| Token | Hex | Usage |
|-------|-----|-------|
| `n50` | `#F7F9FC` | Page backgrounds |
| `n100` | `#EFF2F8` | Card backgrounds |
| `n200` | `#DFE4EE` | Borders, dividers |
| `n300` | `#C3CAD9` | Disabled states |
| `n500` | `#6B7590` | Secondary text |
| `n700` | `#313A52` | Body text |
| `n900` | `#0B1330` | Headings on light |

### Contrast rules that are easy to get wrong

- **Lime never carries text.** It holds the mark, fills a chip, tints a
  selected row. Text on lime fails at any size.
- **Brand green is borderline for text.** Around 3:1 against white — fine for
  large text, buttons and UI, not for small body copy. Use `primary-deep`.
- **Navy disappears on dark grounds.** In a dark theme, lime becomes primary
  and navy is replaced; inverting the light theme naively does not work.

### Print Colors

Not yet specified for the current palette. The previous table listed Pantone
matches for the retired teal and gold brand and has been removed rather than
left to mislead.

Two of these are hard to reproduce in print and should be matched by a printer
against a physical proof rather than converted arithmetically:

- **Signal Lime `#87FF51`** sits outside CMYK gamut. Expect a noticeably duller
  result, or specify a spot colour.
- **Brand Green `#37AD00`** is also out of gamut at full saturation.

Get a Pantone match commissioned before any printed material goes out.

---

## Typography

**Nunito** for both headings and body, per the branding settings.

| Role | Font | Weights |
|------|------|---------|
| Headings | Nunito | 700, 800 |
| Body | Nunito | 400, 500, 600 |
| Data / monospace | JetBrains Mono | 400 |

Monospace is not set in the branding settings. It is used for anything that
counts or ticks — timers, reference numbers, tallies, chunk counts — so figures
do not shift as values change.

Where Nunito is unavailable, fall back to the platform's *rounded* face
(`ui-rounded` in CSS, `.SF Pro Rounded` on Apple) rather than the default
humanist sans; it is far closer.

### Type Scale

| Token | Size | Line Height | Usage |
|-------|------|-------------|-------|
| `display` | 36px / 2.25rem | 1.2 | Hero sections |
| `h1` | 28px / 1.75rem | 1.3 | Page titles |
| `h2` | 22px / 1.375rem | 1.35 | Section headings |
| `h3` | 18px / 1.125rem | 1.4 | Card titles |
| `body` | 14px / 0.875rem | 1.6 | Default body |
| `small` | 12px / 0.75rem | 1.5 | Captions, metadata |

### Google Fonts Import

```html
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
```

---

## Voice & Tone

| Dimension | Always | Never |
|-----------|--------|-------|
| Tone | Professional, assured, clear | Casual, slang, hype |
| Language | Precise, structured, bilingual | Vague, overly technical |
| Personality | Dependable advisor | Trendy disruptor |
| Authority | Governance-literate, compliant | Preachy, bureaucratic |

---

## Brand Archetype

**The Sage** (with Ruler undertones) — trustworthy, authoritative, structured, intelligent.

---

## UI Application Quick Reference

| Element | Spec |
|---------|------|
| Primary CTA | Brand Green (`#37AD00`), white text |
| Headings | Deep Navy (`#01266E`), Nunito 700–800 |
| Body text | `#313A52` on light, Nunito 400 |
| Secondary text | `#6B7590` |
| Nav / active state | Brand Green |
| Selected row tint | Soft Green (`#EFFBE7`) |
| Card backgrounds | White, `#DFE4EE` border |
| Page background | `#F7F9FC` |
| Small text on colour | `#2B8A00`, never `#37AD00` |
| Recording indicator | `#EF4444`, reserved — nothing else uses it |

---

## Imagery

- **Photography:** Clean, high-key. Malaysian/SEA diversity.
- **Icons:** 1.5px stroke, rounded corners, brand green. No gradients, no 3D.
- **Patterns:** None currently defined. The old three-dot pattern is retired
  along with the mark it came from.

---

## Files

| File | Purpose |
|------|---------|
| `docs/brand/BRAND-REFERENCE.md` | This file — developer quick reference |
| Branding settings (super admin) | **Source of truth** for colour, type and logo |
| `docs/brand/antaraNote-Brand-Book.docx` | **Stale** — describes the teal and gold brand |
| `docs/brand/generate-brand-book.js` | **Stale** — regenerates the old DOCX |
| `docs/brand/logos/final-*.svg` | **Stale** — the retired three-dot mark |
| `docs/brand/logos/generate-logos-final.js` | **Stale** — regenerates the old mark |
| `public/design-system.html` | **Stale** — purple palette, unrelated to any current brand |
| `public/icons/icon-*.png` | **Stale** — purple PWA icons |

### Known consumers of the brand

| Consumer | Reads from |
|----------|-----------|
| Web app | `BrandingService` → branding settings |
| Emails | `BrandingService::logoUrl()` |
| Mobile app | `mobile/lib/core/theme/app_colors.dart`, mirrored by hand |
| Mobile logo | `mobile/assets/images/logo-{mark,lockup}.png` |

The mobile theme is a hand-kept mirror, not a live read. Changing the branding
settings does **not** restyle the app — update `app_colors.dart` too.
