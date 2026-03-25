# antaraFlow Starter Kit — Branding Guide

> How to customise this starter kit for your own system in 5 steps.

---

## How Branding Works

All brand colors, fonts, and shapes are controlled by **CSS custom properties** in a preset file. The starter kit ships with two presets:

| Preset | Description |
|--------|-------------|
| `html/presets/preset-blank.css` | Neutral/generic (violet primary, Inter font, white sidebar) |
| `html/presets/preset-antaranote.css` | antaraNote brand (teal primary, Plus Jakarta Sans, deep teal sidebar) |

Each HTML page loads a preset like this:
```html
<link rel="stylesheet" href="presets/preset-blank.css">
```

To switch brand, change this `<link>` to point to a different preset — or edit `preset-blank.css` directly.

---

## Step-by-Step: Rebrand for Your System

### Step 1: Choose your primary color

Open `html/presets/preset-blank.css` and change `--brand-primary`:

```css
:root {
    --brand-primary: #0D7377;   /* Change this to your brand color */
}
```

All shade variants (50–900) are auto-generated from this one value using `color-mix()` in `assets/app.css`. You don't need to manually set any other color values.

### Step 2: Set your sidebar color

By default, the sidebar background matches the brand primary. If you want a different color (e.g. a darker variant, or white):

```css
:root {
    --brand-primary: #0D7377;
    --brand-sidebar-bg: #095153;   /* Darker variant for sidebar */
    --brand-sidebar-text: #ffffff;
}
```

For a light sidebar (like the blank preset):
```css
--brand-sidebar-bg: #ffffff;
--brand-sidebar-text: #334155;  /* slate-700 */
```

### Step 3: Set your fonts

Replace the font values and update the Google Fonts import URL:

```css
@import url('https://fonts.googleapis.com/css2?family=YOUR+HEADING+FONT:wght@600;700&family=YOUR+BODY+FONT:wght@400;500&display=swap');

:root {
    --brand-font-heading: 'Your Heading Font', sans-serif;
    --brand-font-body:    'Your Body Font', sans-serif;
}
```

If you prefer system fonts (no Google Fonts dependency):
```css
:root {
    --brand-font-heading: system-ui, -apple-system, sans-serif;
    --brand-font-body:    system-ui, -apple-system, sans-serif;
}
```

### Step 4: Update the logo

In `_sidebar.html`, find the logo section:

```html
<!-- OPTION A: Image logo (uncomment and set src) -->
<!-- <img src="/path/to/logo.svg" alt="AppName" class="h-7 w-auto max-w-[140px] object-contain"> -->

<!-- OPTION B: Text initials + name (default) -->
<span class="text-violet-600 dark:text-violet-400 font-black text-sm ...">AB</span>
<span class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">AppName</span>
```

- For image logo: Uncomment Option A and set `src` to your logo path
- For text logo: Change `AB` (initials) and `AppName` to your values

Also update `login.html` which has its own logo section.

### Step 5: Update the app name

Search for `AppName` across all HTML files and replace with your app name:

```bash
grep -rn "AppName" starter-kit/html/
# Then replace in each file
```

---

## Creating a New Preset

To create a new branded preset for another system, copy `preset-blank.css`:

```bash
cp html/presets/preset-blank.css html/presets/preset-myapp.css
```

Then edit `preset-myapp.css` with your values. In each HTML page, change:

```html
<link rel="stylesheet" href="presets/preset-blank.css">
```
to:
```html
<link rel="stylesheet" href="presets/preset-myapp.css">
```

---

## Color Reference

If you're unsure what hex value to use, here are some well-tested brand colors that work with the auto-shade system:

| Name | Hex | Notes |
|------|-----|-------|
| Violet (default) | `#7c3aed` | Original antaraFlow brand |
| Nusantara Teal | `#0D7377` | antaraNote brand |
| Ocean Blue | `#0ea5e9` | Cool, modern SaaS |
| Emerald | `#059669` | Finance, sustainability |
| Rose | `#e11d48` | Bold, consumer apps |
| Slate | `#475569` | Neutral, enterprise |
| Amber | `#d97706` | Warm, energetic |

---

## What NOT to Change

These values are part of the design system and should not be changed per brand:

- `dark:bg-slate-*` — neutral dark mode backgrounds (always slate, never gray)
- Status colors (green/amber/red/sky) — universal semantic meaning
- `rounded-xl` on buttons/inputs — part of the design system shape language
- Spacing tokens (`p-6`, `gap-4`, etc.) — ensure visual rhythm
