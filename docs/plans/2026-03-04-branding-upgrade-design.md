# Branding Upgrade Design

**Date:** 2026-03-04
**Status:** Approved

## Overview

Rework the Super Admin branding page to support file uploads (logo, favicon, login background), an extended color palette, font selection, a live preview panel, theme presets (built-in + custom saved), and a reset-to-default function.

## Approach

Alpine.js + standard multipart form submission. No new dependencies — Alpine.js is already in the stack. BrandingController handles file uploads directly.

## Architecture

### Layout

Two-column layout:
- **Left (60%)** — form sections
- **Right (40%, sticky)** — live preview panel

### File Storage

- Files stored in `storage/app/public/branding/`
- Served via `storage/branding/` (symlink)
- Fallback: if no file uploaded, existing URL field value is used

### New Platform Settings Keys

Added to `BrandingService::DEFAULTS`:
- `accent_color` — default `#10b981`
- `danger_color` — default `#ef4444`
- `success_color` — default `#22c55e`
- `heading_font` — default `Inter`
- `body_font` — default `Inter`
- `custom_themes` — default `[]` (JSON array)
- `logo_path` — default `''`
- `favicon_path` — default `''`
- `login_background_path` — default `''`

## Form Sections (Left Column)

1. **Theme Presets** — clickable preset cards at top, + "Save Current" button + custom theme cards
2. **Basic** — App Name, Logo upload (with preview), Favicon upload (with 32x32 preview)
3. **Colors** — Primary, Secondary, Accent, Danger, Success — each has color picker + hex input
4. **Typography** — Heading Font + Body Font dropdowns (10 Google Fonts: Inter, Poppins, Roboto, Lato, Montserrat, Open Sans, Nunito, Raleway, Source Sans Pro, DM Sans)
5. **Login Page** — Background image upload with preview
6. **Contact & Footer** — Footer Text, Support Email
7. **Advanced** — Custom CSS (monospace textarea), Email Header/Footer HTML, Custom Domain

### File Upload UX

- Click-to-browse OR drag-drop area
- Instant thumbnail preview on file select (via FileReader API)
- "Remove" button to clear file and revert to URL field
- Validation: image types only, max 2MB logo/favicon, max 5MB background

## Live Preview Panel (Right Column, Sticky)

Two tabs:

### Sidebar Tab
Mock mini sidebar showing:
- Logo image (or app name initials if no logo)
- App name
- Primary color as sidebar background
- 3-4 dummy nav items with accent color on hover

### Login Tab
Mock login page showing:
- Background image (blurred)
- Logo centered
- Mock login card with primary color button

Updates in real-time via Alpine.js `@input`/`@change` watchers — no server round-trips.

## Theme Presets

### Built-in Presets (5)

| Name | Primary | Secondary | Accent | Heading Font |
|------|---------|-----------|--------|--------------|
| Default Purple | #7c3aed | #3b82f6 | #10b981 | Inter |
| Ocean Blue | #0ea5e9 | #06b6d4 | #f59e0b | Poppins |
| Forest Green | #16a34a | #15803d | #3b82f6 | Nunito |
| Sunset Orange | #ea580c | #dc2626 | #7c3aed | Montserrat |
| Minimal Dark | #374151 | #6b7280 | #f3f4f6 | Inter |

Click preset → populate all color + font fields + live preview updates.

### Custom Themes

- **Save Current as Theme** button → JS prompt for name → AJAX POST to `admin.branding.presets.store`
- Stored as JSON in `platform_settings` key `custom_themes`
- Displayed as removable cards alongside built-in presets
- Delete via AJAX DELETE to `admin.branding.presets.destroy`

### Reset to Default

Button that populates all form fields with hardcoded default values via Alpine.js — no server call needed.

## Backend Changes

### BrandingController

- `update()` — handle `logo`, `favicon`, `login_background` as `UploadedFile`, store to `storage/app/public/branding/`, save path to settings
- Add `storePreset(Request $request)` — save custom theme to `custom_themes` JSON
- Add `destroyPreset(Request $request, string $name)` — remove custom theme by name

### Routes (new)

```
POST   admin/branding/presets          admin.branding.presets.store
DELETE admin/branding/presets/{name}   admin.branding.presets.destroy
```

### Validation

- `logo`: `nullable|image|max:2048`
- `favicon`: `nullable|image|max:2048`
- `login_background`: `nullable|image|max:5120`
- `accent_color`, `danger_color`, `success_color`: `nullable|string|max:7`
- `heading_font`, `body_font`: `nullable|string|max:100`

## Testing

- Update `BrandingTest.php` — add tests for file upload, new color fields, font fields
- Test preset store/destroy endpoints
- Test file validation (wrong type, too large)
