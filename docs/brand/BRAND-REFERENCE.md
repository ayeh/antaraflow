# antaraNote Brand Reference

> Condensed brand guide for developers and implementers. Full brand book: `antaraNote-Brand-Book.docx`

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

## Logo — Spaced Gold Mark

3 dots in a horizontal row: 2 teal dots close together + 1 gold dot spaced apart. The gap = "antara" (between). Gold = the outcome/note.

```
●● · ●    ←  Deep Teal, Teal, [gap], Gold
```

**Construction:**
- Dot 1: Deep Teal `#095153` — Dot 2: Nusantara Teal `#0D7377` — Dot 3: Amber Gold `#D97706`
- Tight gap (dots 1–2): 23px center-to-center
- Wide gap (dots 2–3): 34px center-to-center — the "antara"
- Dot radius: 9px at 1x scale
- Subtle dashed line (gold, 30% opacity) bridges the gap

**Variations:** Primary (mark+wordmark), Stacked, Icon Only, Wordmark Only, Reverse (on navy), Monochrome (single teal).

**Rules:**
- Clear space = height of "a" on all sides
- Minimum size: 24px height (digital), 10mm (print)
- Never close the "antara" gap or rearrange dot order
- Always on solid backgrounds

---

## Color System

### Primary

| Token | Name | Hex | Tailwind |
|-------|------|-----|----------|
| `primary` | Nusantara Teal | `#0D7377` | `text-[#0D7377]` / `bg-[#0D7377]` |
| `primary-dark` | Deep Teal | `#095153` | `text-[#095153]` / `bg-[#095153]` |
| `primary-light` | Soft Teal | `#E6F4F4` | `text-[#E6F4F4]` / `bg-[#E6F4F4]` |

### Secondary

| Token | Name | Hex | Tailwind |
|-------|------|-----|----------|
| `secondary` | Slate Navy | `#1E293B` | `text-slate-800` |
| `secondary-light` | Cool Gray | `#64748B` | `text-slate-500` |

### Accent

| Token | Name | Hex | Tailwind |
|-------|------|-----|----------|
| `accent` | Amber Gold | `#D97706` | `text-amber-600` / `bg-amber-600` |

### Semantic

| Token | Name | Hex | Tailwind |
|-------|------|-----|----------|
| `success` | Emerald | `#059669` | `text-emerald-600` |
| `warning` | Warm Amber | `#F59E0B` | `text-amber-400` |
| `danger` | Crimson | `#DC2626` | `text-red-600` |
| `info` | Sky Blue | `#0284C7` | `text-sky-600` |

### Neutrals

| Token | Hex | Tailwind | Usage |
|-------|-----|----------|-------|
| `neutral-50` | `#F8FAFC` | `bg-slate-50` | Page backgrounds |
| `neutral-100` | `#F1F5F9` | `bg-slate-100` | Card backgrounds |
| `neutral-200` | `#E2E8F0` | `border-slate-200` | Borders, dividers |
| `neutral-300` | `#CBD5E1` | `text-slate-300` | Disabled states |
| `neutral-700` | `#334155` | `text-slate-700` | Body text |
| `neutral-900` | `#0F172A` | `text-slate-900` | Headings |

### Print Colors (CMYK / Pantone)

| Color | CMYK | Pantone |
|-------|------|---------|
| Nusantara Teal | 89, 3, 0, 53 | 7714 C |
| Deep Teal | 89, 2, 0, 67 | 7722 C |
| Slate Navy | 49, 31, 0, 77 | 533 C |
| Amber Gold | 0, 45, 97, 15 | 144 C |

---

## Typography

| Role | Font | Weights | CSS |
|------|------|---------|-----|
| Display / Headlines | Plus Jakarta Sans | 600, 700 | `font-family: 'Plus Jakarta Sans', sans-serif` |
| Body | Inter | 400, 500 | `font-family: 'Inter', sans-serif` |
| Monospace / Data | JetBrains Mono | 400 | `font-family: 'JetBrains Mono', monospace` |

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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&family=Inter:wght@400;500&family=JetBrains+Mono&display=swap" rel="stylesheet">
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
| Sidebar | Teal (`#0D7377`) background, white text |
| Headers / Nav | Slate Navy (`#1E293B`) |
| Primary CTA | Amber Gold (`#D97706`) |
| Body text | Slate 700 (`#334155`) |
| Headings | Slate 900 (`#0F172A`), Plus Jakarta Sans |
| Body font | Inter |
| Card backgrounds | Slate 50 (`#F8FAFC`) or Slate 100 (`#F1F5F9`) |
| Borders | Slate 200 (`#E2E8F0`) |
| Login screen | Dot Pattern at 5% opacity, centered logo, teal button |

---

## Imagery

- **Photography:** Clean, high-key, teal duotone overlay. Malaysian/SEA diversity.
- **Icons:** 1.5px stroke, rounded corners, teal primary. No gradients, no 3D.
- **Patterns:** Dot Pattern (three-dot motif repeated at 5% opacity in staggered grid), grid dots.

---

## Files

| File | Purpose |
|------|---------|
| `docs/brand/antaraNote-Brand-Book.docx` | Full 20-page brand book (bilingual) |
| `docs/brand/generate-brand-book.js` | Script to regenerate the DOCX |
| `docs/brand/BRAND-REFERENCE.md` | This file — developer quick reference |
| `docs/brand/logos/final-*.svg` | Final Spaced Gold logo suite (6 variations) |
| `docs/brand/logos/generate-logos-final.js` | Script to regenerate final logos |
