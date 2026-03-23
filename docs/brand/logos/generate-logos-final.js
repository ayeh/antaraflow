/**
 * antaraNote Final Logo Generator
 *
 * Concept: Spaced Gold — 2 teal dots close + 1 gold dot spaced apart
 * The gap = "antara" (between). Gold = the outcome/note.
 *
 * Full set:
 *   1. Icon only (mark)
 *   2. Primary lockup (icon + wordmark horizontal)
 *   3. Stacked lockup (icon above wordmark)
 *   4. Wordmark only
 *   5. Reverse (white on navy)
 *   6. Monochrome (single teal)
 */

const fs = require("fs");
const path = require("path");

const C = {
  teal:      "#0D7377",
  deepTeal:  "#095153",
  navy:      "#1E293B",
  gold:      "#D97706",
  white:     "#FFFFFF",
  softTeal:  "#E6F4F4",
};

// ── Core mark geometry ──────────────────────────────────────────────────────
const DOT_R = 9;
const TIGHT_GAP = 23;    // gap between 2 teal dots (center to center)
const WIDE_GAP = 34;     // gap before gold dot (the "antara")

function spacedGoldMark(ox, oy, scale, colors = {}) {
  const c1 = colors.dot1 || C.deepTeal;
  const c2 = colors.dot2 || C.teal;
  const c3 = colors.dot3 || C.gold;
  const dashColor = colors.dash || C.gold;
  const dashOpacity = colors.dashOpacity ?? 0.3;
  const r = DOT_R * scale;
  const tg = TIGHT_GAP * scale;
  const wg = WIDE_GAP * scale;

  const x1 = ox;
  const x2 = ox + tg;
  const x3 = ox + tg + wg;
  const cy = oy;

  // Subtle dashed line across the "antara" gap
  const dashLine = dashOpacity > 0
    ? `<line x1="${x2 + r + 1}" y1="${cy}" x2="${x3 - r - 1}" y2="${cy}" stroke="${dashColor}" stroke-width="${1.5 * scale}" stroke-dasharray="${2.5 * scale} ${2.5 * scale}" opacity="${dashOpacity}" stroke-linecap="round"/>`
    : "";

  return {
    content: `
  ${dashLine}
  <circle cx="${x1}" cy="${cy}" r="${r}" fill="${c1}"/>
  <circle cx="${x2}" cy="${cy}" r="${r}" fill="${c2}"/>
  <circle cx="${x3}" cy="${cy}" r="${r}" fill="${c3}"/>`,
    width: tg + wg + r * 2,
    height: r * 2,
    cx: (x1 + x3) / 2,
  };
}

function wordmarkText(x, y, size, color, anchor = "start") {
  return `<text x="${x}" y="${y}" font-family="'Plus Jakarta Sans','Inter','Helvetica Neue',Arial,sans-serif" font-size="${size}" fill="${color}" dominant-baseline="central" text-anchor="${anchor}">
    <tspan font-weight="400">antara</tspan><tspan font-weight="700">Note</tspan>
  </text>`;
}

// ── Build all variations ────────────────────────────────────────────────────
const outDir = __dirname;
const PAD = 12;

// 1. Icon Only
const iconScale = 1;
const iconMark = spacedGoldMark(PAD + DOT_R, 30, iconScale);
const iconW = iconMark.width + PAD * 2;
const iconH = 60;

const iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${iconW.toFixed(0)} ${iconH}" width="${iconW.toFixed(0)}" height="${iconH}">
  <title>antaraNote</title>${iconMark.content}
</svg>`;

// 2. Primary Lockup (horizontal)
const lockupMarkX = PAD + DOT_R;
const lockupH = 56;
const lockupMark = spacedGoldMark(lockupMarkX, lockupH / 2, 1);
const lockupTextX = lockupMarkX + lockupMark.width + 16;
const lockupW = lockupTextX + 200;

const primarySvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${lockupW.toFixed(0)} ${lockupH}" width="${lockupW.toFixed(0)}" height="${lockupH}">
  <title>antaraNote</title>${lockupMark.content}
  ${wordmarkText(lockupTextX, lockupH / 2, 30, C.navy)}
</svg>`;

// 3. Stacked Lockup (icon above wordmark)
const stackScale = 1.1;
const stackMarkX = 130 - (TIGHT_GAP + WIDE_GAP) * stackScale / 2;
const stackMarkY = 28;
const stackMark = spacedGoldMark(stackMarkX, stackMarkY, stackScale);
const stackTextY = stackMarkY + DOT_R * stackScale + 30;
const stackH = stackTextY + 16;

const stackedSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 ${stackH.toFixed(0)}" width="260" height="${stackH.toFixed(0)}">
  <title>antaraNote</title>${stackMark.content}
  ${wordmarkText(130, stackTextY, 26, C.navy, "middle")}
</svg>`;

// 4. Wordmark Only
const wordmarkSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 50" width="280" height="50">
  <title>antaraNote</title>
  ${wordmarkText(16, 25, 30, C.navy)}
</svg>`;

// 5. Reverse (on navy background)
const revMark = spacedGoldMark(lockupMarkX, lockupH / 2, 1, {
  dot1: "#4DB8BB",
  dot2: "#1A9599",
  dot3: C.gold,
  dash: C.gold,
  dashOpacity: 0.35,
});

const reverseSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${lockupW.toFixed(0)} ${lockupH}" width="${lockupW.toFixed(0)}" height="${lockupH}">
  <title>antaraNote — Reverse</title>
  <rect width="${lockupW.toFixed(0)}" height="${lockupH}" fill="${C.navy}" rx="6"/>${revMark.content}
  ${wordmarkText(lockupTextX, lockupH / 2, 30, C.white)}
</svg>`;

// 6. Monochrome (single teal, no gold)
const monoMark = spacedGoldMark(lockupMarkX, lockupH / 2, 1, {
  dot1: C.teal,
  dot2: C.teal,
  dot3: C.deepTeal,
  dash: C.teal,
  dashOpacity: 0.2,
});

const monoSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${lockupW.toFixed(0)} ${lockupH}" width="${lockupW.toFixed(0)}" height="${lockupH}">
  <title>antaraNote — Monochrome</title>${monoMark.content}
  ${wordmarkText(lockupTextX, lockupH / 2, 30, C.teal)}
</svg>`;

// ── Write files ─────────────────────────────────────────────────────────────
const files = {
  "final-icon.svg": iconSvg,
  "final-primary-lockup.svg": primarySvg,
  "final-stacked-lockup.svg": stackedSvg,
  "final-wordmark.svg": wordmarkSvg,
  "final-reverse.svg": reverseSvg,
  "final-monochrome.svg": monoSvg,
};

for (const [name, svg] of Object.entries(files)) {
  fs.writeFileSync(path.join(outDir, name), svg, "utf8");
  console.log(`  ✓ ${name}`);
}

// ── Preview ─────────────────────────────────────────────────────────────────
const preview = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>antaraNote — Final Logo Suite</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Inter',sans-serif;background:#F1F5F9;color:#1E293B;padding:40px}
  h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;color:#0D7377;margin-bottom:4px}
  .sub{color:#64748B;font-size:14px;margin-bottom:32px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:1100px}
  .card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
  .card.dark{background:#1E293B}
  .card h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;color:#0D7377;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
  .card.dark h3{color:#94A3B8}
  .card .usage{font-size:11px;color:#94A3B8;margin-top:10px}
  .preview{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:20px;display:flex;align-items:center;justify-content:center;min-height:80px}
  .card.dark .preview{background:#263044;border-color:#334155}
  .preview img{max-width:100%;height:auto}
  .full{grid-column:1/-1}
</style>
</head>
<body>
<h1>antaraNote — Final Logo Suite</h1>
<p class="sub">Concept: Spaced Gold ●● · — "antara" = the gap between discussion and documentation</p>
<div class="grid">

  <div class="card">
    <h3>1. Icon / Mark</h3>
    <div class="preview"><img src="final-icon.svg" alt="Icon"></div>
    <p class="usage">Favicon, app icon, watermark, small spaces</p>
  </div>

  <div class="card full">
    <h3>2. Primary Lockup</h3>
    <div class="preview"><img src="final-primary-lockup.svg" alt="Primary Lockup"></div>
    <p class="usage">Headers, presentations, documents — primary usage</p>
  </div>

  <div class="card">
    <h3>3. Stacked Lockup</h3>
    <div class="preview"><img src="final-stacked-lockup.svg" alt="Stacked Lockup"></div>
    <p class="usage">Login screens, splash, centered layouts</p>
  </div>

  <div class="card">
    <h3>4. Wordmark Only</h3>
    <div class="preview"><img src="final-wordmark.svg" alt="Wordmark"></div>
    <p class="usage">Inline text, partnerships, co-branding</p>
  </div>

  <div class="card dark">
    <h3>5. Reverse</h3>
    <div class="preview"><img src="final-reverse.svg" alt="Reverse"></div>
    <p class="usage">Dark backgrounds, footers, night mode</p>
  </div>

  <div class="card">
    <h3>6. Monochrome</h3>
    <div class="preview"><img src="final-monochrome.svg" alt="Monochrome"></div>
    <p class="usage">Print constraints, watermarks, single-color</p>
  </div>

</div>
</body>
</html>`;

fs.writeFileSync(path.join(outDir, "preview-final.html"), preview, "utf8");
console.log(`  ✓ preview-final.html\n\nDone! Final logo suite generated.`);
