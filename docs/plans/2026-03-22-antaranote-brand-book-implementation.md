# antaraNote Brand Book Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Produce a 20-page bilingual (EN/BM) brand book as DOCX + a condensed markdown brand reference file.

**Architecture:** Single Node.js script using docx-js to generate the brand book DOCX. Markdown reference written separately. No new project dependencies — docx is installed globally.

**Tech Stack:** Node.js, docx-js (global), Plus Jakarta Sans + Inter fonts (embedded text, not font files)

**Scripts directory:** `/Users/ayeh/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/f2a6cf68-51a2-43b8-bdfd-ed6e3617734e/56a2c8fa-bc18-4cf1-89d1-00be31a7b6ef/skills/docx/scripts`

---

### Task 1: Install docx globally and verify

**Step 1: Install docx**

Run: `npm install -g docx`

**Step 2: Verify installation**

Run: `node -e "const d = require('docx'); console.log('docx version:', Object.keys(d).length, 'exports')"`
Expected: prints export count without error

---

### Task 2: Create the DOCX brand book generator script

**Files:**
- Create: `docs/brand/generate-brand-book.js`

**Step 1: Create the docs/brand directory**

Run: `mkdir -p docs/brand`

**Step 2: Write the generator script**

Create `docs/brand/generate-brand-book.js` — a single Node.js script that generates the 20-page brand book using docx-js.

**Brand book content structure (20 pages):**

Each page maps to a section in the approved design doc at `docs/plans/2026-03-22-antaranote-brand-identity-design.md`.

**Page 1 — Cover:**
- Brand name "antaraNote" in Plus Jakarta Sans Bold, large
- Tagline: "Between Words and Action" / "Antara Kata dan Tindakan"
- Subtitle: "Brand Identity Guidelines / Panduan Identiti Jenama"
- Nusantara Teal (#0D7377) accent bar

**Page 2 — Table of Contents / Isi Kandungan:**
- Bilingual TOC with page references
- Use dot leaders for page numbers

**Page 3 — Brand Story / Kisah Jenama:**
- Origin of "antara" (between/among)
- Mission: "To bridge the gap between meeting discussions and documented outcomes through intelligent, governance-ready documentation." / "Menjambatani jurang antara perbincangan mesyuarat dan hasil yang didokumentasikan melalui dokumentasi pintar yang sedia untuk tadbir urus."
- Vision: "Every formal meeting, perfectly documented." / "Setiap mesyuarat rasmi, didokumentasikan dengan sempurna."

**Page 4 — Brand Archetype & Personality / Arketaip & Personaliti Jenama:**
- The Sage with Ruler undertones
- 4 personality traits: Trustworthy, Structured, Intelligent, Authoritative
- EN/BM descriptions

**Page 5 — Voice & Tone Matrix / Matriks Suara & Nada:**
- Table: Dimension | Always | Never (bilingual)
- Tone, Language, Personality, Authority rows

**Page 6 — Messaging Hierarchy / Hierarki Pemesejan:**
- Primary, Secondary, Tertiary messages
- Sample copy for each tier (EN/BM)

**Page 7 — Tagline & Boilerplate / Tagline & Perenggan Piawai:**
- Primary tagline: "Between Words and Action" / "Antara Kata dan Tindakan"
- Secondary tagline: "Where Decisions Are Documented" / "Di Mana Keputusan Didokumentasikan"
- 25-word boilerplate (EN/BM)
- 50-word boilerplate (EN/BM)

**Page 8 — Logo Primary / Logo Utama:**
- Description of The Quorum Dot concept
- 3 overlapping circles forming Venn diagram
- Primary lockup description (icon + wordmark horizontal)
- Construction grid notes

**Page 9 — Logo Variations / Variasi Logo:**
- Table of 6 variations: Primary, Stacked, Icon Only, Wordmark Only, Reverse, Monochrome
- Usage context for each

**Page 10 — Logo Usage Rules / Peraturan Penggunaan Logo:**
- Clear space rules
- Minimum size (24px digital, 10mm print)
- Do's and Don'ts list

**Page 11 — Color System / Sistem Warna:**
- Primary palette table: Nusantara Teal, Deep Teal, Soft Teal
- Secondary palette: Slate Navy, Cool Gray
- Accent: Amber Gold
- Each with Hex, RGB, CMYK, Pantone values
- Color swatches via shaded table cells

**Page 12 — Color Usage / Penggunaan Warna:**
- Do's: Primary for CTAs, Navy for text, Gold for highlights
- Don'ts: Never use primary on busy backgrounds, never modify opacity
- Accessibility: WCAG AA contrast ratios noted

**Page 13 — Typography / Tipografi:**
- Plus Jakarta Sans specimen (Display, H1, H2, H3)
- Inter specimen (Body, Small)
- JetBrains Mono specimen (Data, IDs)
- Type scale table

**Page 14 — Typography in Use / Tipografi dalam Penggunaan:**
- Example: page title + body text + caption
- Example: dashboard heading + card content
- Font pairing rules

**Page 15 — Imagery & Photography / Imej & Fotografi:**
- Photography direction description
- Treatment: teal duotone overlay
- Cultural representation notes
- Do's and Don'ts

**Page 16 — Iconography & Patterns / Ikonografi & Corak:**
- Icon style: 1.5px stroke, rounded, teal
- The Quorum Pattern description
- Grid dots pattern description

**Page 17 — Digital Applications / Aplikasi Digital:**
- SaaS UI spec summary
- Login screen spec
- Email template spec
- Social media spec

**Page 18 — Print Applications / Aplikasi Cetakan:**
- Business card spec (navy, white/gold logo)
- Letterhead spec
- Slide deck spec (title slide + content slide)

**Page 19 — Document Templates / Templat Dokumen:**
- MoM PDF export spec
- Invoice spec
- Report spec

**Page 20 — Brand Governance / Tadbir Urus Jenama:**
- Brand ownership
- Approval process for brand usage
- Contact information placeholder
- Version control note

**Script requirements:**
- Use A4 page size (11906 x 16838 DXA)
- 1-inch margins (1440 DXA)
- Color swatches as shaded table cells using ShadingType.CLEAR
- Use WidthType.DXA for all tables
- Page breaks between each page/section
- Header: "antaraNote Brand Guidelines" on all pages except cover
- Footer: Page numbers on all pages
- Output to: `docs/brand/antaraNote-Brand-Book.docx`

**Step 3: Run the generator**

Run: `node docs/brand/generate-brand-book.js`
Expected: Creates `docs/brand/antaraNote-Brand-Book.docx`

**Step 4: Validate the DOCX**

Run: `python3 "/Users/ayeh/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/f2a6cf68-51a2-43b8-bdfd-ed6e3617734e/56a2c8fa-bc18-4cf1-89d1-00be31a7b6ef/skills/docx/scripts/office/validate.py" docs/brand/antaraNote-Brand-Book.docx`
Expected: Validation passes

---

### Task 3: Write the markdown brand reference

**Files:**
- Create: `docs/brand/BRAND-REFERENCE.md`

**Step 1: Write condensed brand reference**

A single markdown file with the essential brand specs developers need:
- Brand name, tagline
- Color tokens (Hex only, mapped to Tailwind classes)
- Typography (fonts, scale)
- Logo usage quick rules
- Voice guidelines summary

This is the dev-friendly condensed version of the full brand book.

**Step 2: Verify markdown renders correctly**

Read the file back and check formatting.

---

### Task 4: Commit deliverables

**Step 1: Stage files**

```bash
git add docs/brand/generate-brand-book.js docs/brand/antaraNote-Brand-Book.docx docs/brand/BRAND-REFERENCE.md docs/plans/2026-03-22-antaranote-brand-identity-design.md
```

**Step 2: Commit**

```bash
git commit -m "feat: add antaraNote brand identity — brand book (DOCX) + developer reference (MD)"
```
