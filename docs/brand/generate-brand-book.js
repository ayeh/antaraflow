/**
 * antaraNote Brand Book Generator
 *
 * Generates a 20-page bilingual (English/Malay) brand identity guidelines document
 * using docx-js. Output: antaraNote-Brand-Book.docx
 */

const {
  Document,
  Packer,
  Paragraph,
  TextRun,
  Table,
  TableRow,
  TableCell,
  Header,
  Footer,
  PageNumber,
  PageBreak,
  PositionalTab,
  PositionalTabAlignment,
  PositionalTabLeader,
  PositionalTabRelativeTo,
  HeadingLevel,
  AlignmentType,
  WidthType,
  ShadingType,
  BorderStyle,
  LevelFormat,
  VerticalAlign,
  TableLayoutType,
  NumberFormat,
  convertInchesToTwip,
} = require("docx");
const fs = require("fs");
const path = require("path");

// ── Constants ──────────────────────────────────────────────────────────────────
const PAGE_WIDTH = 11906; // A4 width in DXA
const PAGE_HEIGHT = 16838; // A4 height in DXA
const MARGIN = 1440; // 1 inch in DXA
const CONTENT_WIDTH = PAGE_WIDTH - 2 * MARGIN; // 9026 DXA

const COLORS = {
  primaryTeal: "0D7377",
  deepTeal: "095153",
  softTeal: "E6F4F4",
  slateNavy: "1E293B",
  coolGray: "64748B",
  amberGold: "D97706",
  success: "059669",
  warning: "F59E0B",
  danger: "DC2626",
  info: "0284C7",
  neutral50: "F8FAFC",
  neutral100: "F1F5F9",
  neutral200: "E2E8F0",
  neutral300: "CBD5E1",
  neutral700: "334155",
  neutral900: "0F172A",
  white: "FFFFFF",
  black: "000000",
};

const FONT = {
  display: "Plus Jakarta Sans",
  body: "Inter",
  mono: "JetBrains Mono",
};

// ── Helpers ────────────────────────────────────────────────────────────────────

const NO_BORDERS = {
  top: { style: BorderStyle.NONE, size: 0 },
  bottom: { style: BorderStyle.NONE, size: 0 },
  left: { style: BorderStyle.NONE, size: 0 },
  right: { style: BorderStyle.NONE, size: 0 },
};

function pageBreak() {
  return new Paragraph({ children: [new PageBreak()] });
}

function spacer(pts = 12) {
  return new Paragraph({
    spacing: { before: pts * 20, after: 0 },
    children: [],
  });
}

function heading1(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_1,
    children: [
      new TextRun({
        text,
        font: FONT.display,
        bold: true,
        size: 56,
        color: COLORS.primaryTeal,
      }),
    ],
    spacing: { after: 200 },
  });
}

function heading2(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_2,
    children: [
      new TextRun({
        text,
        font: FONT.display,
        bold: true,
        size: 44,
        color: COLORS.deepTeal,
      }),
    ],
    spacing: { after: 160 },
  });
}

function heading3(text) {
  return new Paragraph({
    children: [
      new TextRun({
        text,
        font: FONT.display,
        bold: true,
        size: 36,
        color: COLORS.slateNavy,
      }),
    ],
    spacing: { after: 120 },
  });
}

function bodyText(text, opts = {}) {
  return new Paragraph({
    spacing: { after: 120 },
    alignment: opts.alignment,
    children: [
      new TextRun({
        text,
        font: opts.font || FONT.body,
        size: opts.size || 24,
        color: opts.color || COLORS.slateNavy,
        bold: opts.bold || false,
        italics: opts.italics || false,
      }),
    ],
  });
}

function bilingualBlock(enLabel, enText, bmLabel, bmText) {
  return [
    bodyText(`${enLabel}: ${enText}`, { bold: false }),
    bodyText(`${bmLabel}: ${bmText}`, { italics: true, color: COLORS.coolGray }),
  ];
}

function labeledParagraph(label, text, opts = {}) {
  return new Paragraph({
    spacing: { after: 120 },
    children: [
      new TextRun({
        text: `${label}: `,
        font: FONT.body,
        bold: true,
        size: opts.size || 24,
        color: opts.labelColor || COLORS.primaryTeal,
      }),
      new TextRun({
        text,
        font: FONT.body,
        size: opts.size || 24,
        color: opts.color || COLORS.slateNavy,
      }),
    ],
  });
}

function bulletItem(text, level = 0) {
  return new Paragraph({
    numbering: { reference: "bullet-list", level },
    spacing: { after: 80 },
    children: [
      new TextRun({
        text,
        font: FONT.body,
        size: 22,
        color: COLORS.slateNavy,
      }),
    ],
  });
}

function makeCell(text, opts = {}) {
  const children = [];
  if (typeof text === "string") {
    children.push(
      new Paragraph({
        spacing: { before: 40, after: 40 },
        alignment: opts.alignment || AlignmentType.LEFT,
        children: [
          new TextRun({
            text,
            font: opts.font || FONT.body,
            size: opts.fontSize || 20,
            color: opts.textColor || COLORS.slateNavy,
            bold: opts.bold || false,
          }),
        ],
      })
    );
  } else if (Array.isArray(text)) {
    children.push(...text);
  }

  const cellOpts = {
    width: { size: opts.width || 1500, type: WidthType.DXA },
    verticalAlign: VerticalAlign.CENTER,
    children,
  };

  if (opts.shading) {
    cellOpts.shading = {
      type: ShadingType.CLEAR,
      fill: opts.shading,
      color: "auto",
    };
  }

  if (opts.borders) {
    cellOpts.borders = opts.borders;
  }

  return new TableCell(cellOpts);
}

function makeHeaderCell(text, width) {
  return makeCell(text, {
    width,
    bold: true,
    textColor: COLORS.white,
    shading: COLORS.primaryTeal,
    fontSize: 20,
  });
}

function simpleTable(headers, rows, columnWidths) {
  const tableWidth = columnWidths.reduce((a, b) => a + b, 0);
  const headerRow = new TableRow({
    children: headers.map((h, i) => makeHeaderCell(h, columnWidths[i])),
    tableHeader: true,
  });

  const dataRows = rows.map(
    (row, ri) =>
      new TableRow({
        children: row.map((cell, ci) => {
          if (typeof cell === "object" && cell !== null && !Array.isArray(cell)) {
            return makeCell(cell.text || "", {
              width: columnWidths[ci],
              ...cell,
            });
          }
          return makeCell(cell, {
            width: columnWidths[ci],
            shading: ri % 2 === 0 ? COLORS.neutral50 : COLORS.white,
          });
        }),
      })
  );

  return new Table({
    width: { size: tableWidth, type: WidthType.DXA },
    columnWidths,
    rows: [headerRow, ...dataRows],
    layout: TableLayoutType.FIXED,
  });
}

function swatchCell(color, width) {
  return new TableCell({
    width: { size: width, type: WidthType.DXA },
    shading: { type: ShadingType.CLEAR, fill: color, color: "auto" },
    verticalAlign: VerticalAlign.CENTER,
    children: [
      new Paragraph({
        spacing: { before: 40, after: 40 },
        children: [new TextRun({ text: " ", size: 20 })],
      }),
    ],
  });
}

function colorRow(name, hex, rgb, cmyk, pantone, columnWidths) {
  return new TableRow({
    children: [
      swatchCell(hex.replace("#", ""), columnWidths[0]),
      makeCell(name, { width: columnWidths[1], bold: true }),
      makeCell(hex, { width: columnWidths[2], font: FONT.mono, fontSize: 18 }),
      makeCell(rgb, { width: columnWidths[3], fontSize: 18 }),
      makeCell(cmyk, { width: columnWidths[4], fontSize: 18 }),
      makeCell(pantone, { width: columnWidths[5], fontSize: 18 }),
    ],
  });
}

// ── Page Generators ────────────────────────────────────────────────────────────

function createCoverPage() {
  return [
    spacer(80),
    spacer(80),
    spacer(80),
    new Paragraph({
      alignment: AlignmentType.LEFT,
      spacing: { after: 0 },
      children: [
        new TextRun({
          text: "antaraNote",
          font: FONT.display,
          bold: true,
          size: 144,
          color: COLORS.primaryTeal,
        }),
      ],
    }),
    spacer(6),
    // Teal accent bar via paragraph with bottom border
    new Paragraph({
      spacing: { after: 300 },
      border: {
        bottom: {
          style: BorderStyle.SINGLE,
          size: 24,
          color: COLORS.primaryTeal,
          space: 8,
        },
      },
      children: [new TextRun({ text: " ", size: 4 })],
    }),
    new Paragraph({
      spacing: { after: 120 },
      children: [
        new TextRun({
          text: "Between Words and Action",
          font: FONT.display,
          bold: true,
          size: 36,
          color: COLORS.slateNavy,
        }),
      ],
    }),
    new Paragraph({
      spacing: { after: 300 },
      children: [
        new TextRun({
          text: "Antara Kata dan Tindakan",
          font: FONT.display,
          italics: true,
          size: 32,
          color: COLORS.coolGray,
        }),
      ],
    }),
    spacer(40),
    new Paragraph({
      spacing: { after: 80 },
      children: [
        new TextRun({
          text: "Brand Identity Guidelines",
          font: FONT.display,
          size: 28,
          color: COLORS.deepTeal,
        }),
      ],
    }),
    new Paragraph({
      spacing: { after: 200 },
      children: [
        new TextRun({
          text: "Panduan Identiti Jenama",
          font: FONT.display,
          italics: true,
          size: 26,
          color: COLORS.coolGray,
        }),
      ],
    }),
    spacer(40),
    new Paragraph({
      children: [
        new TextRun({
          text: "2026",
          font: FONT.display,
          size: 28,
          color: COLORS.coolGray,
        }),
      ],
    }),
    pageBreak(),
  ];
}

function createTOCPage() {
  const tocEntries = [
    ["Brand Story / Kisah Jenama", "3"],
    ["Brand Archetype / Arketaip Jenama", "4"],
    ["Voice & Tone / Suara & Nada", "5"],
    ["Messaging Hierarchy / Hierarki Pemesejan", "6"],
    ["Tagline & Boilerplate / Tagline & Perenggan Piawai", "7"],
    ["Logo Primary / Logo Utama", "8"],
    ["Logo Variations / Variasi Logo", "9"],
    ["Logo Usage Rules / Peraturan Penggunaan Logo", "10"],
    ["Color System / Sistem Warna", "11"],
    ["Color Usage / Penggunaan Warna", "12"],
    ["Typography / Tipografi", "13"],
    ["Typography in Use / Tipografi dalam Penggunaan", "14"],
    ["Imagery & Photography / Imej & Fotografi", "15"],
    ["Iconography & Patterns / Ikonografi & Corak", "16"],
    ["Digital Applications / Aplikasi Digital", "17"],
    ["Print Applications / Aplikasi Cetakan", "18"],
    ["Document Templates / Templat Dokumen", "19"],
    ["Brand Governance / Tadbir Urus Jenama", "20"],
  ];

  const items = [
    heading1("Table of Contents"),
    bodyText("Isi Kandungan", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(12),
  ];

  for (const [title, pageNum] of tocEntries) {
    items.push(
      new Paragraph({
        spacing: { after: 100 },
        children: [
          new TextRun({
            text: title,
            font: FONT.body,
            size: 22,
            color: COLORS.slateNavy,
          }),
          new TextRun({
            children: [
              new PositionalTab({
                alignment: PositionalTabAlignment.RIGHT,
                relativeTo: PositionalTabRelativeTo.MARGIN,
                leader: PositionalTabLeader.DOT,
              }),
              pageNum,
            ],
            font: FONT.body,
            size: 22,
            color: COLORS.primaryTeal,
            bold: true,
          }),
        ],
      })
    );
  }

  items.push(pageBreak());
  return items;
}

function createBrandStoryPage() {
  return [
    heading1("Brand Story"),
    bodyText("Kisah Jenama", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("The Origin of 'antara'"),
    bodyText(
      'The word "antara" means "between" or "among" in Malay. It captures the essence of what happens in every meeting: the space between discussion and documentation, between words spoken and actions taken. antaraNote exists in that space \u2014 bridging the gap between what was discussed and what gets done.'
    ),
    spacer(6),
    bodyText(
      'The suffix "Note" grounds the brand in its core function: intelligent meeting notes and minutes. Together, antaraNote represents the definitive bridge between conversation and documented outcomes.',
      { color: COLORS.coolGray }
    ),
    spacer(12),
    heading3("Mission / Misi"),
    ...bilingualBlock(
      "EN",
      "To bridge the gap between meeting discussions and documented outcomes through intelligent, governance-ready documentation.",
      "BM",
      "Menjambatani jurang antara perbincangan mesyuarat dan hasil yang didokumentasikan melalui dokumentasi pintar yang sedia untuk tadbir urus."
    ),
    spacer(12),
    heading3("Vision / Visi"),
    ...bilingualBlock(
      "EN",
      "Every formal meeting, perfectly documented.",
      "BM",
      "Setiap mesyuarat rasmi, didokumentasikan dengan sempurna."
    ),
    pageBreak(),
  ];
}

function createArchetypePage() {
  const traits = [
    [
      "Trustworthy / Boleh Dipercayai",
      "We are the reliable record of truth. Every document produced is accurate, complete, and tamper-evident.",
      "Kami adalah rekod kebenaran yang boleh dipercayai. Setiap dokumen yang dihasilkan adalah tepat, lengkap, dan tahan gangguan.",
    ],
    [
      "Structured / Berstruktur",
      "We bring order to complexity. Meetings become organised, searchable, and actionable records.",
      "Kami membawa keteraturan kepada kerumitan. Mesyuarat menjadi rekod yang teratur, boleh dicari, dan boleh ditindaklanjuti.",
    ],
    [
      "Intelligent / Pintar",
      "We enhance human capability with AI. Smart transcription, automatic action items, and intelligent formatting work silently in the background.",
      "Kami meningkatkan keupayaan manusia dengan AI. Transkripsi pintar, item tindakan automatik, dan pemformatan pintar berfungsi secara senyap di latar belakang.",
    ],
    [
      "Authoritative / Berwibawa",
      "We understand governance and compliance. Our platform speaks the language of boardrooms, government agencies, and corporate secretaries.",
      "Kami memahami tadbir urus dan pematuhan. Platform kami berbicara dalam bahasa bilik lembaga, agensi kerajaan, dan setiausaha syarikat.",
    ],
  ];

  const colWidths = [2200, 3400, 3400];

  return [
    heading1("Brand Archetype"),
    bodyText("Arketaip Jenama", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("The Sage with Ruler Undertones"),
    bodyText(
      "antaraNote embodies The Sage archetype \u2014 wise, knowledgeable, and truth-seeking \u2014 with undertones of The Ruler: structured, authoritative, and governance-minded. We are the dependable advisor who brings clarity and order to meeting documentation."
    ),
    spacer(12),
    heading3("Core Traits"),
    simpleTable(
      ["Trait / Sifat", "Description (EN)", "Penerangan (BM)"],
      traits.map((t) => [t[0], t[1], t[2]]),
      colWidths
    ),
    pageBreak(),
  ];
}

function createVoiceTonePage() {
  const colWidths = [1800, 3600, 3600];
  const matrix = [
    ["Tone", "Professional, assured, clear", "Casual, slang, hype"],
    ["Language", "Precise, structured, bilingual", "Vague, overly technical"],
    ["Personality", "Dependable advisor", "Trendy disruptor"],
    ["Authority", "Governance-literate, compliant", "Preachy, bureaucratic"],
  ];

  return [
    heading1("Voice & Tone"),
    bodyText("Suara & Nada", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Voice Matrix / Matriks Suara"),
    simpleTable(
      ["Dimension", "Always", "Never"],
      matrix,
      colWidths
    ),
    spacer(12),
    heading3("Writing Guidelines / Panduan Penulisan"),
    bulletItem("Use active voice. State facts confidently."),
    bulletItem("Keep sentences concise \u2014 aim for 15\u201320 words maximum."),
    bulletItem("Use bilingual headings where appropriate (EN primary, BM secondary)."),
    bulletItem("Avoid jargon unless it is governance or compliance terminology."),
    bulletItem("Use title case for headings, sentence case for body text."),
    bulletItem("Prefer specific numbers over vague qualifiers (e.g., '3 action items' not 'several items')."),
    pageBreak(),
  ];
}

function createMessagingPage() {
  const colWidths = [1500, 3760, 3760];
  const rows = [
    [
      "Primary",
      "Between Words and Action \u2014 antaraNote transforms meeting discussions into structured, governance-ready documentation.",
      "Antara Kata dan Tindakan \u2014 antaraNote mentransformasikan perbincangan mesyuarat menjadi dokumentasi berstruktur sedia tadbir urus.",
    ],
    [
      "Secondary",
      "Where Decisions Are Documented \u2014 intelligent meeting minutes that capture every decision, action item, and follow-up.",
      "Di Mana Keputusan Didokumentasikan \u2014 minit mesyuarat pintar yang merakam setiap keputusan, item tindakan, dan susulan.",
    ],
    [
      "Tertiary",
      "Stop taking minutes. Start making records. \u2014 AI-powered transcription meets governance-grade documentation.",
      "Berhenti mengambil minit. Mula membuat rekod. \u2014 Transkripsi berkuasa AI bertemu dokumentasi gred tadbir urus.",
    ],
  ];

  return [
    heading1("Messaging Hierarchy"),
    bodyText("Hierarki Pemesejan", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    simpleTable(
      ["Tier", "English", "Bahasa Melayu"],
      rows,
      colWidths
    ),
    pageBreak(),
  ];
}

function createTaglinePage() {
  return [
    heading1("Tagline & Boilerplate"),
    bodyText("Tagline & Perenggan Piawai", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Primary Tagline"),
    labeledParagraph("EN", "Between Words and Action"),
    labeledParagraph("BM", "Antara Kata dan Tindakan"),
    spacer(6),
    heading3("Secondary Tagline"),
    labeledParagraph("EN", "Where Decisions Are Documented"),
    labeledParagraph("BM", "Di Mana Keputusan Didokumentasikan"),
    spacer(12),
    heading3("25-Word Boilerplate"),
    bodyText(
      "EN: antaraNote is the intelligent meeting documentation platform that transforms discussions into structured, governance-ready minutes with AI-powered transcription, action tracking, and approval workflows."
    ),
    bodyText(
      "BM: antaraNote ialah platform dokumentasi mesyuarat pintar yang mentransformasikan perbincangan menjadi minit berstruktur sedia tadbir urus dengan transkripsi berkuasa AI, penjejakan tindakan, dan aliran kerja kelulusan.",
      { italics: true, color: COLORS.coolGray }
    ),
    spacer(12),
    heading3("50-Word Boilerplate"),
    bodyText(
      "EN: antaraNote is the intelligent meeting documentation platform purpose-built for Malaysian GLCs, government agencies, and enterprises. It transforms meeting discussions into structured, governance-ready minutes using AI-powered transcription, automated action item extraction, and multi-level approval workflows \u2014 ensuring every decision is captured, tracked, and compliant with organisational governance standards."
    ),
    bodyText(
      "BM: antaraNote ialah platform dokumentasi mesyuarat pintar yang dibina khas untuk GLC Malaysia, agensi kerajaan, dan perusahaan. Ia mentransformasikan perbincangan mesyuarat menjadi minit berstruktur sedia tadbir urus menggunakan transkripsi berkuasa AI, pengekstrakan item tindakan automatik, dan aliran kerja kelulusan pelbagai peringkat \u2014 memastikan setiap keputusan dirakam, dijejak, dan mematuhi piawaian tadbir urus organisasi.",
      { italics: true, color: COLORS.coolGray }
    ),
    pageBreak(),
  ];
}

function createLogoPrimaryPage() {
  return [
    heading1("Logo Primary"),
    bodyText("Logo Utama", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("The Spaced Gold Mark"),
    bodyText(
      "The antaraNote logo is called 'Spaced Gold.' It consists of three dots in a horizontal row: two teal dots placed close together, and one gold dot spaced slightly apart. The deliberate gap between the teal pair and the gold dot represents 'antara' \u2014 the space between discussion and documentation."
    ),
    bodyText(
      "The two teal dots (Deep Teal #095153 and Nusantara Teal #0D7377) represent the meeting participants and their discussions. The gold dot (Amber Gold #D97706) represents the outcome \u2014 the documented note that emerges from the meeting. A subtle dashed line bridges the gap, symbolising the act of capturing what was discussed."
    ),
    spacer(12),
    heading3("Primary Lockup"),
    bodyText(
      "The primary lockup places the Spaced Gold mark to the left of the 'antaraNote' wordmark in a horizontal arrangement. The mark and wordmark are optically aligned and maintain a fixed relationship."
    ),
    spacer(6),
    heading3("Construction Notes"),
    bulletItem("Three dots of equal diameter (18px at 1x scale)."),
    bulletItem("Dot 1 (Deep Teal #095153) and Dot 2 (Nusantara Teal #0D7377) are spaced 23px centre-to-centre."),
    bulletItem("Dot 2 and Dot 3 (Amber Gold #D97706) are spaced 34px centre-to-centre \u2014 the 'antara' gap."),
    bulletItem("A subtle dashed line (1.5px, gold, 30% opacity) bridges the gap between Dot 2 and Dot 3."),
    bulletItem("Wordmark uses Plus Jakarta Sans: 'antara' in Regular (400), 'Note' in Bold (700)."),
    bulletItem("The 'a' in 'antara' is lowercase; the 'N' in 'Note' is uppercase."),
    pageBreak(),
  ];
}

function createLogoVariationsPage() {
  const colWidths = [1800, 2400, 4800];
  const rows = [
    ["Primary", "Horizontal mark + wordmark", "Default for all standard applications. Use on white or light backgrounds."],
    ["Stacked", "Mark above wordmark", "For square or vertical spaces such as login screens, splash screens, and centred layouts."],
    ["Icon Only", "Spaced Gold mark only", "For small-scale applications where the wordmark is not legible (e.g., favicons, app badges)."],
    ["Wordmark Only", "Text only, no mark", "For contexts where the mark has already been established (e.g., within the app UI)."],
    ["Reverse", "Light dots on dark background", "For use on dark backgrounds including Slate Navy. Teal dots lighten to #4DB8BB and #1A9599."],
    ["Monochrome", "Single-colour version", "For single-colour printing, embossing, or watermarks. All three dots in teal shades."],
  ];

  return [
    heading1("Logo Variations"),
    bodyText("Variasi Logo", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    simpleTable(
      ["Variation", "Description", "Usage Context"],
      rows,
      colWidths
    ),
    pageBreak(),
  ];
}

function createLogoUsagePage() {
  return [
    heading1("Logo Usage Rules"),
    bodyText("Peraturan Penggunaan Logo", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Clear Space / Ruang Kosong"),
    bodyText(
      'Maintain a minimum clear space around the logo equal to the height of the lowercase "a" in the wordmark on all four sides. No other graphic elements, text, or visual clutter should encroach on this space.'
    ),
    spacer(6),
    heading3("Minimum Size / Saiz Minimum"),
    bulletItem("Digital: 24px height minimum"),
    bulletItem("Print: 10mm height minimum"),
    spacer(12),
    heading3("Do / Lakukan"),
    bulletItem("Use the logo on approved brand colours and white backgrounds."),
    bulletItem("Maintain the fixed proportions, spacing, and dot sizes of the mark."),
    bulletItem("Use the reverse version on dark backgrounds."),
    bulletItem("Ensure sufficient contrast between the logo and its background."),
    bulletItem("Use provided logo files only \u2014 do not recreate the logo."),
    spacer(6),
    heading3("Don't / Jangan"),
    bulletItem("Do not stretch, skew, or distort the logo."),
    bulletItem("Do not change the logo colours outside of approved variations."),
    bulletItem("Do not place the logo on busy photographic backgrounds without a container."),
    bulletItem("Do not add drop shadows, outlines, or effects to the logo."),
    bulletItem("Do not rearrange the dot order or close the 'antara' gap."),
    bulletItem("Do not rotate the logo."),
    pageBreak(),
  ];
}

function createColorSystemPage() {
  const colWidths = [800, 1800, 1200, 1800, 1800, 1600];
  const headers = ["Swatch", "Name", "Hex", "RGB", "CMYK", "Pantone"];

  const primaryRows = [
    colorRow("Nusantara Teal", "#0D7377", "13, 115, 119", "89, 3, 0, 53", "7714 C", colWidths),
    colorRow("Deep Teal", "#095153", "9, 81, 83", "89, 2, 0, 67", "7722 C", colWidths),
    colorRow("Soft Teal", "#E6F4F4", "230, 244, 244", "6, 0, 0, 4", "\u2014", colWidths),
  ];

  const secondaryRows = [
    colorRow("Slate Navy", "#1E293B", "30, 41, 59", "49, 31, 0, 77", "533 C", colWidths),
    colorRow("Cool Gray", "#64748B", "100, 116, 139", "28, 17, 0, 45", "7545 C", colWidths),
  ];

  const accentRows = [
    colorRow("Amber Gold", "#D97706", "217, 119, 6", "0, 45, 97, 15", "144 C", colWidths),
    colorRow("Emerald", "#059669", "5, 150, 105", "\u2014", "\u2014", colWidths),
    colorRow("Warm Amber", "#F59E0B", "245, 158, 11", "\u2014", "\u2014", colWidths),
    colorRow("Crimson", "#DC2626", "220, 38, 38", "\u2014", "\u2014", colWidths),
    colorRow("Sky Blue", "#0284C7", "2, 132, 199", "\u2014", "\u2014", colWidths),
  ];

  const headerRow = new TableRow({
    children: headers.map((h, i) => makeHeaderCell(h, colWidths[i])),
    tableHeader: true,
  });

  const tableWidth = colWidths.reduce((a, b) => a + b, 0);

  return [
    heading1("Color System"),
    bodyText("Sistem Warna", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Primary Palette"),
    new Table({
      width: { size: tableWidth, type: WidthType.DXA },
      columnWidths: colWidths,
      rows: [headerRow, ...primaryRows],
      layout: TableLayoutType.FIXED,
    }),
    spacer(12),
    heading3("Secondary Palette"),
    new Table({
      width: { size: tableWidth, type: WidthType.DXA },
      columnWidths: colWidths,
      rows: [
        new TableRow({
          children: headers.map((h, i) => makeHeaderCell(h, colWidths[i])),
          tableHeader: true,
        }),
        ...secondaryRows,
      ],
      layout: TableLayoutType.FIXED,
    }),
    spacer(12),
    heading3("Accent & Semantic Colors"),
    new Table({
      width: { size: tableWidth, type: WidthType.DXA },
      columnWidths: colWidths,
      rows: [
        new TableRow({
          children: headers.map((h, i) => makeHeaderCell(h, colWidths[i])),
          tableHeader: true,
        }),
        ...accentRows,
      ],
      layout: TableLayoutType.FIXED,
    }),
    pageBreak(),
  ];
}

function createColorUsagePage() {
  return [
    heading1("Color Usage"),
    bodyText("Penggunaan Warna", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Usage Guidelines / Panduan Penggunaan"),
    bulletItem("Nusantara Teal: Primary CTAs, links, active states, and brand accent elements."),
    bulletItem("Deep Teal: Hover states, active navigation, and emphasis areas."),
    bulletItem("Soft Teal: Backgrounds for highlighted sections, cards, and info panels."),
    bulletItem("Slate Navy: Primary text, headings, and navigation backgrounds."),
    bulletItem("Cool Gray: Secondary text, labels, and placeholder text."),
    bulletItem("Amber Gold: Highlights, badges, notifications, and premium features."),
    spacer(12),
    heading3("Accessibility / Kebolehcapaian"),
    bodyText(
      "All colour combinations must meet WCAG 2.1 AA contrast requirements. Key ratios:"
    ),
    bulletItem("Nusantara Teal on White: 4.6:1 (passes AA for normal text)"),
    bulletItem("Slate Navy on White: 12.6:1 (passes AAA)"),
    bulletItem("White on Deep Teal: 5.8:1 (passes AA)"),
    bulletItem("Amber Gold on White: 3.2:1 (passes AA for large text only \u2014 use sparingly)"),
    spacer(12),
    heading3("Do / Lakukan"),
    bulletItem("Use Nusantara Teal as the primary brand colour across all touchpoints."),
    bulletItem("Pair Teal with Slate Navy for professional, high-contrast layouts."),
    bulletItem("Use Amber Gold sparingly for attention-drawing elements."),
    spacer(6),
    heading3("Don't / Jangan"),
    bulletItem("Do not use Amber Gold for large text blocks or backgrounds."),
    bulletItem("Do not combine Danger red with Warning amber in the same UI element."),
    bulletItem("Do not use colours at reduced opacity for critical interface elements."),
    pageBreak(),
  ];
}

function createTypographyPage() {
  const colWidths = [2000, 2500, 1200, 1200, 2100];
  const rows = [
    ["Display", "Plus Jakarta Sans", "Bold (700)", "36px", "Headlines, hero sections"],
    ["H1", "Plus Jakarta Sans", "Bold (700)", "28px", "Page titles"],
    ["H2", "Plus Jakarta Sans", "SemiBold (600)", "22px", "Section headings"],
    ["H3", "Plus Jakarta Sans", "SemiBold (600)", "18px", "Subsection headings"],
    ["Body", "Inter", "Regular (400)", "14px", "Paragraphs, content"],
    ["Body Medium", "Inter", "Medium (500)", "14px", "Labels, emphasis"],
    ["Small", "Inter", "Regular (400)", "12px", "Captions, footnotes"],
    ["Monospace", "JetBrains Mono", "Regular (400)", "14px", "Code, data fields"],
  ];

  return [
    heading1("Typography"),
    bodyText("Tipografi", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Font Families"),
    labeledParagraph("Display/Headlines", "Plus Jakarta Sans \u2014 a modern geometric sans-serif with humanist touches. Used for all display and heading typography."),
    labeledParagraph("Body", "Inter \u2014 a highly legible sans-serif optimised for screen readability. Used for all body text and interface elements."),
    labeledParagraph("Monospace", "JetBrains Mono \u2014 a developer-grade monospace font. Used for code snippets, data fields, and technical content."),
    spacer(12),
    heading3("Type Scale"),
    simpleTable(
      ["Role", "Font Family", "Weight", "Size", "Usage"],
      rows,
      colWidths
    ),
    pageBreak(),
  ];
}

function createTypographyInUsePage() {
  return [
    heading1("Typography in Use"),
    bodyText("Tipografi dalam Penggunaan", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Font Pairing Rules"),
    bulletItem("Plus Jakarta Sans for headings paired with Inter for body text creates a professional, modern hierarchy."),
    bulletItem("Never mix more than two sans-serif fonts in a single layout."),
    bulletItem("JetBrains Mono is reserved exclusively for code, data, and technical fields."),
    spacer(12),
    heading3("Where to Use What"),
    bulletItem("Marketing pages: Plus Jakarta Sans Bold for headlines, Inter Regular for body."),
    bulletItem("Application UI: Inter Medium for labels and navigation, Inter Regular for content."),
    bulletItem("Data tables: Inter Regular for cell content, Inter Medium for column headers."),
    bulletItem("Code blocks and data fields: JetBrains Mono Regular."),
    bulletItem("PDF exports (MoM): Plus Jakarta Sans for document title, Inter for all content."),
    spacer(12),
    heading3("Example Combinations"),
    new Paragraph({
      spacing: { after: 80 },
      children: [
        new TextRun({ text: "Meeting Minutes: Q4 Board Review", font: FONT.display, bold: true, size: 36, color: COLORS.slateNavy }),
      ],
    }),
    new Paragraph({
      spacing: { after: 80 },
      children: [
        new TextRun({ text: "Section: Financial Overview", font: FONT.display, bold: true, size: 28, color: COLORS.primaryTeal }),
      ],
    }),
    new Paragraph({
      spacing: { after: 80 },
      children: [
        new TextRun({
          text: "The committee reviewed quarterly financial performance and approved the proposed budget allocation for the upcoming fiscal year.",
          font: FONT.body,
          size: 24,
          color: COLORS.slateNavy,
        }),
      ],
    }),
    new Paragraph({
      spacing: { after: 80 },
      children: [
        new TextRun({
          text: "Action Item: FIN-2026-042",
          font: FONT.mono,
          size: 22,
          color: COLORS.coolGray,
        }),
      ],
    }),
    pageBreak(),
  ];
}

function createImageryPage() {
  return [
    heading1("Imagery & Photography"),
    bodyText("Imej & Fotografi", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Photography Direction"),
    bodyText(
      "antaraNote imagery should convey professionalism, collaboration, and clarity. Photography should feel authentic and relatable to Malaysian corporate and government contexts."
    ),
    spacer(6),
    heading3("Style Guidelines"),
    bulletItem("Natural lighting preferred; avoid harsh studio lighting."),
    bulletItem("Clean, modern office environments \u2014 boardrooms, meeting rooms, collaborative spaces."),
    bulletItem("People in professional attire appropriate to Malaysian corporate and government settings."),
    bulletItem("Diverse representation of Malaysian demographics."),
    bulletItem("Technology integration shown naturally (laptops, screens, projectors)."),
    spacer(12),
    heading3("Teal Duotone Treatment"),
    bodyText(
      "Hero images and feature graphics may use a teal duotone treatment to maintain brand consistency. Apply using Nusantara Teal (#0D7377) as the shadow colour and Soft Teal (#E6F4F4) as the highlight colour."
    ),
    spacer(12),
    heading3("Cultural Representation"),
    bulletItem("Reflect Malaysia's multicultural workforce authentically."),
    bulletItem("Include representation of formal government and GLC settings."),
    bulletItem("Show both traditional and modern meeting environments."),
    spacer(6),
    heading3("Don't / Jangan"),
    bulletItem("Do not use generic Western-only stock photography."),
    bulletItem("Do not use overly casual or informal meeting settings."),
    bulletItem("Do not use images that contradict the professional, governance-ready brand positioning."),
    pageBreak(),
  ];
}

function createIconographyPage() {
  return [
    heading1("Iconography & Patterns"),
    bodyText("Ikonografi & Corak", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Icon Style"),
    bulletItem("Line icons with 1.5px stroke weight."),
    bulletItem("Rounded end caps and joins for a friendly yet professional feel."),
    bulletItem("Primary colour: Nusantara Teal (#0D7377)."),
    bulletItem("Secondary colour: Slate Navy (#1E293B) for inactive or secondary states."),
    bulletItem("Icon grid: 24x24px with 2px padding."),
    bulletItem("Consistent visual weight across all icons in the set."),
    spacer(12),
    heading3("The Dot Pattern"),
    bodyText(
      "A branded pattern derived from the Spaced Gold mark. Consists of the three-dot motif (two teal, one gold) repeated at reduced opacity in a staggered grid arrangement. Used for backgrounds, dividers, and decorative elements."
    ),
    bulletItem("Pattern opacity: 5\u201310% on light backgrounds, 10\u201315% on dark backgrounds."),
    bulletItem("Colours: Nusantara Teal and Deep Teal only."),
    bulletItem("Never use the pattern at full opacity or in competing colours."),
    spacer(12),
    heading3("Grid Dots Pattern"),
    bodyText(
      "A secondary pattern of evenly spaced small dots (2px diameter, 16px spacing) in Cool Gray (#64748B) at 15% opacity. Used for subtle background texture in UI panels and document templates."
    ),
    pageBreak(),
  ];
}

function createDigitalApplicationsPage() {
  return [
    heading1("Digital Applications"),
    bodyText("Aplikasi Digital", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("SaaS UI Specification"),
    bulletItem("Navigation: Slate Navy (#1E293B) sidebar with white text and teal active indicators."),
    bulletItem("Content area: White (#FFFFFF) background with Neutral-50 (#F8FAFC) card backgrounds."),
    bulletItem("Primary buttons: Nusantara Teal with white text, 6px border radius."),
    bulletItem("Secondary buttons: White with Teal border and Teal text."),
    bulletItem("Typography: Inter for all UI text, Plus Jakarta Sans for page titles."),
    spacer(6),
    heading3("Login Screen"),
    bulletItem("Split layout: left panel with Teal gradient and Dot Pattern, right panel with login form."),
    bulletItem("Logo: Primary lockup centred on left panel."),
    bulletItem("Tagline displayed below logo on left panel."),
    spacer(6),
    heading3("Email Templates"),
    bulletItem("Header: Nusantara Teal bar with white logo."),
    bulletItem("Body: White background, Inter font, Slate Navy text."),
    bulletItem("CTA buttons: Nusantara Teal with white text."),
    bulletItem("Footer: Neutral-100 background with Cool Gray text."),
    spacer(6),
    heading3("Social Media"),
    bulletItem("Profile avatar: Spaced Gold mark on white background."),
    bulletItem("Cover/banner: Teal gradient with Dot Pattern overlay."),
    bulletItem("Post templates: White cards with Teal accent, consistent typography."),
    pageBreak(),
  ];
}

function createPrintApplicationsPage() {
  return [
    heading1("Print Applications"),
    bodyText("Aplikasi Cetakan", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Business Card / Kad Perniagaan"),
    bulletItem("Size: 90mm x 55mm (standard Malaysian/international)."),
    bulletItem("Front: White background, primary lockup top-left, contact details in Inter."),
    bulletItem("Back: Deep Teal background with white reverse logo, Dot Pattern at 10% opacity."),
    bulletItem("Paper: 350gsm matte laminate with spot UV on logo."),
    spacer(12),
    heading3("Letterhead / Kepala Surat"),
    bulletItem("Size: A4 (210mm x 297mm)."),
    bulletItem("Header: Primary lockup top-left, Teal accent line across top."),
    bulletItem("Footer: Company details in Cool Gray, 8pt Inter."),
    bulletItem("Body area: 25mm margins, Inter 11pt for body text."),
    bulletItem("Paper: 120gsm uncoated white."),
    spacer(12),
    heading3("Slide Deck / Dek Slaid"),
    bulletItem("Aspect ratio: 16:9 widescreen."),
    bulletItem("Title slides: Deep Teal background with white text, Dot Pattern accent."),
    bulletItem("Content slides: White background, Slate Navy text, Teal headings."),
    bulletItem("Charts and data: Use brand colour palette in order of hierarchy."),
    bulletItem("Typography: Plus Jakarta Sans for slide titles, Inter for bullet points."),
    pageBreak(),
  ];
}

function createDocumentTemplatesPage() {
  return [
    heading1("Document Templates"),
    bodyText("Templat Dokumen", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("MoM PDF Export / Eksport PDF Minit Mesyuarat"),
    bulletItem("Header: Organisation logo + antaraNote lockup, meeting title in Plus Jakarta Sans Bold."),
    bulletItem("Metadata block: Date, time, venue, attendees in a Soft Teal (#E6F4F4) panel."),
    bulletItem("Agenda items: Numbered with Teal accent, Inter Medium for item titles."),
    bulletItem("Action items: Highlighted in Amber Gold left-border panels."),
    bulletItem("Footer: Page numbers, document reference, classification level."),
    bulletItem("Approval section: Signature lines with role labels."),
    spacer(12),
    heading3("Invoice / Invois"),
    bulletItem("Header: Primary lockup with company details, Teal accent line."),
    bulletItem("Table: Clean borders, Teal header row, alternating Neutral-50 rows."),
    bulletItem("Total section: Bold, right-aligned with Teal highlight."),
    bulletItem("Payment terms: Cool Gray text, 10pt Inter."),
    spacer(12),
    heading3("Report / Laporan"),
    bulletItem("Cover: Deep Teal background with white title text and Dot Pattern."),
    bulletItem("Interior pages: White background, Teal H1 headings, Slate Navy body text."),
    bulletItem("Charts: Brand colour palette, starting with Teal and Amber Gold."),
    bulletItem("Executive summary: Soft Teal panel with key findings."),
    pageBreak(),
  ];
}

function createBrandGovernancePage() {
  return [
    heading1("Brand Governance"),
    bodyText("Tadbir Urus Jenama", { italics: true, color: COLORS.coolGray, size: 28 }),
    spacer(6),
    heading3("Brand Ownership / Pemilikan Jenama"),
    bodyText(
      "The antaraNote brand is owned and managed by the Product & Design team. All brand assets, guidelines, and approvals are coordinated through the Brand Lead."
    ),
    bodyText(
      "Jenama antaraNote dimiliki dan diuruskan oleh pasukan Produk & Reka Bentuk. Semua aset jenama, panduan, dan kelulusan diselaraskan melalui Ketua Jenama.",
      { italics: true, color: COLORS.coolGray }
    ),
    spacer(12),
    heading3("Approval Process / Proses Kelulusan"),
    bulletItem("All external-facing materials must be reviewed by the Brand Lead before publication."),
    bulletItem("Partner and co-branding materials require written approval from the Brand Lead."),
    bulletItem("Modifications to the logo, colours, or typography require explicit sign-off."),
    bulletItem("Third-party usage of brand assets requires a licensing agreement."),
    spacer(12),
    heading3("Contact / Hubungi"),
    bodyText("For brand-related enquiries, asset requests, or approval submissions:"),
    bodyText("Brand Lead, Product & Design Team", { bold: true }),
    bodyText("brand@antaranote.com", { color: COLORS.primaryTeal }),
    spacer(12),
    // Version info
    new Paragraph({
      spacing: { before: 400 },
      border: {
        top: {
          style: BorderStyle.SINGLE,
          size: 6,
          color: COLORS.neutral200,
          space: 12,
        },
      },
      children: [
        new TextRun({
          text: "Version 1.0  |  March 2026",
          font: FONT.body,
          size: 20,
          color: COLORS.coolGray,
        }),
      ],
    }),
    bodyText("antaraNote Brand Identity Guidelines / Panduan Identiti Jenama antaraNote", {
      size: 20,
      color: COLORS.coolGray,
    }),
  ];
}

// ── Document Assembly ──────────────────────────────────────────────────────────

function buildDocument() {
  const doc = new Document({
    creator: "antaraNote",
    title: "antaraNote Brand Identity Guidelines",
    description: "Bilingual (EN/BM) brand book for antaraNote",
    styles: {
      default: {
        document: {
          run: {
            font: FONT.body,
            size: 24,
            color: COLORS.slateNavy,
          },
        },
      },
      paragraphStyles: [
        {
          id: "Heading1",
          name: "Heading 1",
          basedOn: "Normal",
          next: "Normal",
          quickFormat: true,
          paragraph: {
            spacing: { before: 240, after: 120 },
            outlineLevel: 0,
          },
          run: {
            font: FONT.display,
            bold: true,
            size: 56,
            color: COLORS.primaryTeal,
          },
        },
        {
          id: "Heading2",
          name: "Heading 2",
          basedOn: "Normal",
          next: "Normal",
          quickFormat: true,
          paragraph: {
            spacing: { before: 200, after: 100 },
            outlineLevel: 1,
          },
          run: {
            font: FONT.display,
            bold: true,
            size: 44,
            color: COLORS.deepTeal,
          },
        },
      ],
    },
    numbering: {
      config: [
        {
          reference: "bullet-list",
          levels: [
            {
              level: 0,
              format: LevelFormat.BULLET,
              text: "\u2022",
              alignment: AlignmentType.LEFT,
              style: {
                paragraph: {
                  indent: { left: 720, hanging: 360 },
                },
              },
            },
            {
              level: 1,
              format: LevelFormat.BULLET,
              text: "\u2013",
              alignment: AlignmentType.LEFT,
              style: {
                paragraph: {
                  indent: { left: 1440, hanging: 360 },
                },
              },
            },
          ],
        },
      ],
    },
    sections: [
      {
        properties: {
          page: {
            size: {
              width: PAGE_WIDTH,
              height: PAGE_HEIGHT,
            },
            margin: {
              top: MARGIN,
              right: MARGIN,
              bottom: MARGIN,
              left: MARGIN,
            },
          },
          titlePage: true,
        },
        headers: {
          default: new Header({
            children: [
              new Paragraph({
                alignment: AlignmentType.RIGHT,
                children: [
                  new TextRun({
                    text: "antaraNote Brand Guidelines",
                    font: FONT.body,
                    size: 16,
                    color: COLORS.coolGray,
                    italics: true,
                  }),
                ],
              }),
            ],
          }),
          first: new Header({
            children: [new Paragraph({ children: [] })],
          }),
        },
        footers: {
          first: new Footer({
            children: [new Paragraph({ children: [] })],
          }),
          default: new Footer({
            children: [
              new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [
                  new TextRun({
                    children: [PageNumber.CURRENT],
                    font: FONT.body,
                    size: 18,
                    color: COLORS.coolGray,
                  }),
                ],
              }),
            ],
          }),
        },
        children: [
          ...createCoverPage(),
          ...createTOCPage(),
          ...createBrandStoryPage(),
          ...createArchetypePage(),
          ...createVoiceTonePage(),
          ...createMessagingPage(),
          ...createTaglinePage(),
          ...createLogoPrimaryPage(),
          ...createLogoVariationsPage(),
          ...createLogoUsagePage(),
          ...createColorSystemPage(),
          ...createColorUsagePage(),
          ...createTypographyPage(),
          ...createTypographyInUsePage(),
          ...createImageryPage(),
          ...createIconographyPage(),
          ...createDigitalApplicationsPage(),
          ...createPrintApplicationsPage(),
          ...createDocumentTemplatesPage(),
          ...createBrandGovernancePage(),
        ],
      },
    ],
  });

  return doc;
}

// ── Main ───────────────────────────────────────────────────────────────────────

async function main() {
  console.log("Generating antaraNote Brand Book...");

  const doc = buildDocument();
  const buffer = await Packer.toBuffer(doc);

  const outputPath = path.join(__dirname, "antaraNote-Brand-Book.docx");
  fs.writeFileSync(outputPath, buffer);

  console.log(`Brand book generated: ${outputPath}`);
  console.log(`File size: ${(buffer.length / 1024).toFixed(1)} KB`);
}

main().catch((err) => {
  console.error("Error generating brand book:", err);
  process.exit(1);
});
