# antaraFLOW — Product Design Document

**Product:** antaraFLOW — AI-Powered Minutes of Meeting Platform
**Version:** 1.0
**Date:** 28 February 2026
**Author:** antara Product Team
**Status:** Draft — Pending Approval
**Ecosystem:** Part of antara* product family

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Product Identity](#2-product-identity)
3. [Market Research & Competitive Analysis](#3-market-research--competitive-analysis)
4. [Architecture & Technical Stack](#4-architecture--technical-stack)
5. [Feature Modules](#5-feature-modules)
6. [Tiered Pricing & Subscription Model](#6-tiered-pricing--subscription-model)
7. [UX/UI Direction & Page Map](#7-uxui-direction--page-map)
8. [Database Schema](#8-database-schema)
9. [Phased Delivery Roadmap](#9-phased-delivery-roadmap)
10. [Appendix](#10-appendix)

---

## 1. Executive Summary

### Vision

antaraFLOW is a standalone AI-powered Minutes of Meeting (MOM) platform designed to transform how teams capture, manage, and act on meeting outcomes. Built on Laravel (LAMP stack), it offers both cloud SaaS and self-hosted deployment options — serving everyone from solo consultants to enterprise organizations.

### Problem Statement

- Professionals spend 4+ hours/week on meeting documentation
- Action items get lost between meetings
- Meeting decisions are poorly tracked across time
- Existing solutions are cloud-only, privacy-concerning, and expensive for SEA market

### Solution

An AI-first meeting documentation platform that:

- Auto-transcribes audio/video in 40+ languages
- Auto-extracts summaries, action items, decisions, and topics
- Tracks action items across meetings with escalation
- Provides cross-meeting AI search and insights
- Offers self-hosted deployment for data sovereignty
- Supports multiple AI providers (OpenAI, Anthropic, Google, local/Ollama)

### Key Differentiators

| Differentiator | Detail |
|----------------|--------|
| Self-hosted option | Full offline capability with Ollama — rare in market |
| Multi-input fusion | Audio + documents + manual notes combined into single MOM |
| Multi-provider AI | Not locked to one AI vendor — swap freely |
| No bot in meeting | Browser recording — no bot invitation needed |
| SEA market pricing | Affordable tiers for Southeast Asian market |
| antara ecosystem | Integrates with antaraPROJECT and future antara* products |

### Target Audience

**Tiered model** serving both segments:

- **Free / Pro** — Solo professionals, small teams (5-50 people), agencies, startups
- **Business / Enterprise** — Growing organizations, corporates, government, MNCs (50-1000+ users)

---

## 2. Product Identity

### Branding

| Attribute | Value |
|-----------|-------|
| **Product Name** | antaraFLOW |
| **Tagline** | Your meetings, perfectly captured. |
| **Ecosystem** | antara* product family |
| **Domain** | antaraflow.com (or .io / .ai) |

### Name Rationale

"FLOW" represents the natural flow of meeting conversations being captured, structured, and transformed into actionable documentation. It conveys smoothness, continuity, and the seamless nature of the AI-powered experience.

---

## 3. Market Research & Competitive Analysis

### Market Size

- Global AI meeting minutes market: $1.42B (2024) → projected **$7.47B** by 2033
- 33% of knowledge workers already use AI meeting notes tools
- Users report saving **4+ hours per week** with AI meeting tools

### Competitive Landscape

| Platform | Strength | Weakness | Price |
|----------|----------|----------|-------|
| Otter.ai | Enterprise security, real-time transcription | English-centric, no self-hosted | $8-20/user/mo |
| Fireflies.ai | 100+ languages, CRM integration, AI copilot | Credit-burn model, expensive at scale | $10-19/user/mo |
| tl;dv | Generous free tier, 96% accuracy | Limited enterprise features | Free-$19/user/mo |
| Fellow.app | Templates, collaborative agendas | No transcription, meeting-centric | $7/user/mo |
| Sembly AI | 95% action item accuracy | Limited integrations | $10/user/mo |
| Meetily | Open source, self-hosted, offline | Early stage, limited features | Free (OSS) |

### Market Gaps (Our Opportunities)

1. **Self-hosted + AI** — Only Meetily offers this, and it's early stage
2. **Multi-input processing** — Most competitors only do audio
3. **Multi-provider AI** — All competitors lock to their own AI pipeline
4. **SEA market focus** — No competitor specifically targets Southeast Asia
5. **Project management integration** — antaraPROJECT ecosystem advantage
6. **Privacy-first** — Full offline mode with local AI models

### UX/UI Trends (2026)

Based on industry research:

- **Progressive disclosure** — Simple by default, power features revealed gradually
- **AI-personalized interfaces** — Dashboards adapt to each user's patterns
- **Multimodal interaction** — Voice + text + gesture inputs
- **Command palettes** — Cmd+K for power user navigation
- **Dark mode** — Expected standard, not optional
- **Mobile-first** — Record on phone, review on desktop

Sources:

- [12 UI/UX Design Trends 2026 — Index.dev](https://www.index.dev/blog/ui-ux-design-trends)
- [Top 10 SaaS UX Design Trends 2026 — Millipixels](https://millipixels.com/blog/saas-ux-design)
- [10 UX Design Shifts 2026 — UX Collective](https://uxdesign.cc/10-ux-design-shifts-you-cant-ignore-in-2026-8f0da1c6741d)
- [Best Meeting Management Software 2026 — Research.com](https://research.com/software/best-meeting-management-software)
- [Best Meeting Minutes Software 2025 — Jamie](https://www.meetjamie.ai/blog/meeting-minutes-software)

---

## 4. Architecture & Technical Stack

### Approach: Monolith Laravel (Domain-Driven)

Single Laravel application with domain-driven internal structure. AI-heavy processing runs via Laravel Queue workers — isolated from web process but within the same codebase.

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (PHP 8.4) |
| Frontend | Vanilla JS ES6+ / Alpine.js / Tailwind CSS v4 |
| Database | MySQL 8.0 (MariaDB compatible for self-hosted) |
| Queue | Laravel Queue (Redis driver cloud / database driver self-hosted) |
| AI Providers | OpenAI, Anthropic, Google Gemini, Ollama (local) |
| Transcription | OpenAI Whisper, Google Speech-to-Text, local Whisper |
| File Storage | S3-compatible (cloud) / local disk (self-hosted) |
| Cache | Redis (cloud) / file cache (self-hosted) |
| Search | Laravel Scout + Meilisearch (optional) |
| Real-time | Laravel Broadcasting (Pusher/Soketi) |
| Bundler | Vite |
| Testing | Pest v4 |

### Project Structure

```
antaraflow/
├── app/
│   ├── Domain/
│   │   ├── Meeting/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   ├── Policies/
│   │   │   └── Events/
│   │   ├── Transcription/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Jobs/
│   │   │   └── Contracts/
│   │   ├── AI/
│   │   │   ├── Contracts/
│   │   │   ├── Providers/
│   │   │   ├── Services/
│   │   │   └── Config/
│   │   ├── ActionItem/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   └── Controllers/
│   │   ├── Attendee/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   └── Controllers/
│   │   ├── Collaboration/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   └── Controllers/
│   │   ├── Analytics/
│   │   │   ├── Services/
│   │   │   ├── Controllers/
│   │   │   └── Aggregators/
│   │   ├── Export/
│   │   │   ├── Services/
│   │   │   ├── Templates/
│   │   │   └── Controllers/
│   │   └── Account/
│   │       ├── Models/
│   │       ├── Services/
│   │       └── Middleware/
│   ├── Infrastructure/
│   │   ├── AI/
│   │   │   ├── AIProviderFactory.php
│   │   │   ├── AIProviderInterface.php
│   │   │   └── Adapters/
│   │   ├── Storage/
│   │   │   └── StorageManager.php
│   │   └── Tenancy/
│   │       ├── BelongsToOrganization.php
│   │       └── OrganizationScope.php
│   └── Support/
│       ├── Enums/
│       └── Helpers/
├── config/
│   ├── ai.php
│   ├── transcription.php
│   └── antaraflow.php
├── database/
├── resources/
│   ├── views/
│   ├── js/
│   └── css/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
└── tests/
    ├── Feature/
    ├── Unit/
    └── Browser/
```

### AI Provider Architecture (Multi-Provider)

```
AIProviderInterface
├── chat(prompt, context): string
├── summarize(text): MeetingSummary
├── extractActionItems(text): ActionItem[]
├── extractDecisions(text): Decision[]
└── transcribe(audio): Transcript

Implementations:
├── OpenAIProvider      (GPT-4o + Whisper)
├── AnthropicProvider   (Claude Sonnet/Opus)
├── GoogleProvider      (Gemini + Speech-to-Text)
└── OllamaProvider      (Local models — Llama, Mistral)

AIProviderFactory::make(config('ai.default'))
```

Environment configuration:

```env
AI_PROVIDER=openai
AI_MODEL=gpt-4o
TRANSCRIPTION_PROVIDER=openai
TRANSCRIPTION_MODEL=whisper-1
```

### Self-Hosted Deployment

| Aspect | Detail |
|--------|--------|
| Delivery | Docker image + docker-compose.yml |
| License | License key validated offline (JWT-based) |
| Updates | Self-serve via `php artisan antaraflow:update` |
| AI | BYO API keys OR Ollama (full offline) |
| Support | Dedicated channel + deployment assistance |

Self-hosted deployment:

```bash
git clone antaraflow && cd antaraflow
cp .env.example .env
docker-compose up -d
php artisan antaraflow:install   # Guided wizard
```

Install wizard covers:

1. Database connection
2. Admin account creation
3. AI provider setup (OpenAI key / Ollama URL)
4. Storage configuration (local / S3)
5. Email/SMTP setup
6. License key activation

---

## 5. Feature Modules

### Module Overview

| Module | Name | Features | Must-Have |
|--------|------|----------|-----------|
| A | Core MOM Management | 10 | 7 |
| B | AI Transcription Engine | 12 | 7 |
| C | AI-Powered Extraction | 10 | 5 |
| D | Multi-Input Processing | 8 | 5 |
| E | AI Assistant / Copilot | 8 | 3 |
| F | Action Item Management | 11 | 6 |
| G | Attendee Management | 9 | 4 |
| H | Collaboration | 8 | 5 |
| I | Export & Sharing | 9 | 5 |
| J | Analytics & Insights | 10 | 3 |
| K | Account & Tenancy | 10 | 6 |
| **Total** | | **105** | **56** |

Priority legend: **Must** = MVP required, **Should** = Phase 2, **Nice** = Phase 3+

---

### Module A: Core MOM Management

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| A1 | Create MOM | Must | Create from recording, upload, template, or blank |
| A2 | Edit MOM | Must | Rich text editor for title, summary, notes |
| A3 | Status workflow | Must | Draft → Finalized → Approved with transitions |
| A4 | Meeting series | Must | Recurring meetings (weekly standup, monthly review) |
| A5 | Meeting templates | Must | Agenda presets, reusable configurations |
| A6 | Version history | Must | Rollback to any previous version |
| A7 | Tags & categories | Should | Custom per organization, color-coded |
| A8 | Global search | Must | Full-text search across all MOMs |
| A9 | Duplicate MOM | Should | Clone as template for next meeting |
| A10 | Archive / soft delete | Must | Soft delete with recovery option |

Key User Flow:

```
New MOM:  Choose method → [Record / Upload / Template / Blank]
                ↓
          Fill metadata (title, date, project, attendees)
                ↓
          AI processes input → generates draft
                ↓
          User reviews & edits → Finalize → Share/Approve
```

---

### Module B: AI Transcription Engine

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| B1 | Audio upload transcription | Must | MP3, WAV, M4A, OGG, WEBM support |
| B2 | Browser-based recording | Must | Record directly from browser |
| B3 | Real-time live transcription | Should | Via WebSocket during meeting |
| B4 | Speaker diarization | Must | Identify who said what |
| B5 | Multi-language transcription | Must | Minimum 10 languages |
| B6 | Auto-detect language | Should | Automatic language identification |
| B7 | Noise reduction | Should | Audio enhancement pre-processing |
| B8 | Confidence scoring | Must | Per-segment confidence scores (0-1) |
| B9 | Queue system | Must | Position tracking, ETA estimation |
| B10 | Retry mechanism | Must | Max 3 attempts with exponential backoff |
| B11 | Transcription editing | Must | User can correct AI mistakes |
| B12 | Speaker label editing | Should | Reassign segments to correct speakers |

Processing Pipeline:

```
Audio Input → Validate & Store
      ↓
Queue Job → Select Provider (Whisper / Google / Local)
      ↓
Transcribe → Diarize speakers → Segment with timestamps
      ↓
Store segments → Calculate confidence → Notify user
      ↓
Ready for AI extraction
```

Provider Interface:

```php
interface TranscriberInterface
{
    public function transcribe(string $filePath, array $options): TranscriptionResult;
    public function supportsDiarization(): bool;
    public function supportedLanguages(): array;
    public function maxFileSizeMB(): int;
}
```

---

### Module C: AI-Powered Extraction

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| C1 | Auto-generate summary | Must | AI summary from all inputs |
| C2 | Auto-extract action items | Must | With assignee + deadline |
| C3 | Auto-extract decisions | Must | Key decisions documented |
| C4 | Auto-extract topics/themes | Must | Key discussion topics |
| C5 | Auto-extract open questions | Should | Unresolved items flagged |
| C6 | Sentiment analysis | Should | Meeting mood gauge |
| C7 | Speaker statistics | Should | Talk time, participation % |
| C8 | Custom extraction templates | Should | Configurable output format |
| C9 | Re-generate with different provider | Should | Swap AI and regenerate |
| C10 | AI accuracy feedback | Must | Thumbs up/down per section |

Extraction Pipeline:

```
Transcription + Documents + Manual Notes
            ↓
    Merge into unified context
            ↓
    AI Provider → Structured extraction prompt
            ↓
    Parse response → Validate → Store
            ↓
    Summary | Action Items | Decisions | Topics | Questions
            ↓
    User reviews → Edit → Feedback loop
```

---

### Module D: Multi-Input Processing

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| D1 | Audio file upload | Must | MP3, WAV, M4A, OGG, WEBM |
| D2 | Document upload & extraction | Must | PDF, DOCX, TXT content extraction |
| D3 | Manual notes entry | Must | Rich text editor input |
| D4 | Video file import | Should | Extract audio track from video |
| D5 | Platform transcript import | Should | Zoom, Teams, Meet transcript files |
| D6 | Telegram/WhatsApp audio | Nice | Import voice messages |
| D7 | Combine multiple inputs | Must | Merge all inputs into single MOM |
| D8 | Input status tracking | Must | Pending/processing/completed/failed |

---

### Module E: AI Assistant / Copilot

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| E1 | AI Chat within MOM | Must | Ask questions about meeting content |
| E2 | Cross-meeting AI search | Must | "When did we discuss X?" |
| E3 | AI Meeting Coach | Should | Effectiveness scoring, suggestions |
| E4 | AI Pre-meeting Briefing | Should | Context from previous meetings |
| E5 | AI Agenda Generator | Should | Based on history + open items |
| E6 | AI Follow-up Recommendations | Should | Suggest next steps |
| E7 | Chat history persistence | Must | Stored per MOM |
| E8 | Suggested prompts | Should | Quick action buttons |

Chat Architecture:

```
User asks question
      ↓
Build context: MOM content + transcription + related MOMs
      ↓
AI Provider → Stream response (SSE)
      ↓
Display + Store conversation history
```

---

### Module F: Action Item Management

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| F1 | Priority levels | Must | High / Medium / Low |
| F2 | Status tracking | Must | Pending → In Progress → Done → Cancelled |
| F3 | Assignee management | Must | From attendees or org members |
| F4 | Deadline tracking | Must | With overdue detection |
| F5 | AI auto-assign | Should | Based on meeting context |
| F6 | AI deadline suggestion | Should | Based on complexity analysis |
| F7 | Overdue alerts & escalation | Must | Email notifications |
| F8 | Cross-meeting dashboard | Must | View all action items across MOMs |
| F9 | Action item history | Should | Audit trail of changes |
| F10 | Bulk status update | Should | Multi-select and update |
| F11 | Carry forward | Must | Link items across meetings |

---

### Module G: Attendee Management

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| G1 | Internal attendees | Must | From organization directory |
| G2 | External attendees | Must | Name + email, no account needed |
| G3 | RSVP tracking | Must | Invited/accepted/declined/tentative |
| G4 | Attendee roles | Must | Chairperson, Secretary, Speaker, Participant, Observer |
| G5 | Present/absent marking | Must | Record attendance |
| G6 | QR code registration | Should | Walk-in attendee registration |
| G7 | Attendee groups | Should | Save common groups for reuse |
| G8 | Participation analytics | Should | Per-person trends |
| G9 | Bulk invite | Must | From org directory |

---

### Module H: Collaboration

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| H1 | Threaded comments | Must | Comments on MOM sections |
| H2 | @mentions | Must | Tag users in comments |
| H3 | Emoji reactions | Should | React to MOM sections |
| H4 | Inline annotations | Should | Highlight + comment on specific text |
| H5 | Guest access | Must | Shareable link with token |
| H6 | Client visibility toggle | Must | Show/hide MOM to clients |
| H7 | Real-time presence | Nice | Who's viewing indicator |
| H8 | Notification on activity | Must | Comment, mention, status change |

---

### Module I: Export & Sharing

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| I1 | PDF export | Must | Professional templates |
| I2 | DOCX export | Must | Editable Word format |
| I3 | JSON export | Must | API consumption format |
| I4 | Email auto-distribute | Must | Send MOM to attendees |
| I5 | Shareable link | Must | With optional password protection |
| I6 | Branded templates | Should | Organization logo, colors, footer |
| I7 | Batch export | Should | Multiple MOMs at once |
| I8 | CSV export (action items) | Should | Spreadsheet-friendly format |
| I9 | Print-optimized view | Should | Clean print layout |

---

### Module J: Analytics & Insights

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| J1 | Meeting frequency dashboard | Must | Meetings per week/month |
| J2 | Action item completion rates | Must | By org/team/person |
| J3 | Duration trends | Must | Average meeting duration over time |
| J4 | Topic tracking | Should | Topics across meetings |
| J5 | Participation heatmap | Should | Team engagement visualization |
| J6 | Meeting cost calculator | Should | Hourly rate x attendees x duration |
| J7 | Decision tracking | Should | Decisions traced across meetings |
| J8 | Transcription accuracy stats | Should | Provider performance metrics |
| J9 | AI feedback dashboard | Should | AI accuracy from user feedback |
| J10 | Exportable reports | Should | Download analytics as PDF/CSV |

---

### Module K: Account & Tenancy (Cross-Cutting)

| # | Feature | Priority | Description |
|---|---------|----------|-------------|
| K1 | Organization management | Must | Multi-tenant isolation |
| K2 | User management | Must | Invite, roles, permissions |
| K3 | RBAC | Must | Owner/Admin/Manager/Member/Viewer |
| K4 | Subscription tiers | Must | Free/Pro/Business/Enterprise |
| K5 | AI provider configuration | Must | Per-org provider & key settings |
| K6 | Organization settings | Must | Branding, defaults, retention |
| K7 | SSO / OAuth | Should | SAML, Google, Microsoft |
| K8 | 2FA | Should | TOTP-based two-factor auth |
| K9 | API keys | Should | External API access management |
| K10 | Audit logging | Must | All sensitive actions logged |

---

## 6. Tiered Pricing & Subscription Model

### Tier Comparison

| Feature | Free | Pro | Business | Enterprise |
|---------|------|-----|----------|------------|
| **Price** | $0 | $9/user/mo | $19/user/mo | Custom |
| **Users** | 1 | Up to 10 | Up to 50 | Unlimited |
| **MOMs/month** | 5 | 50 | Unlimited | Unlimited |
| **Transcription** | 60 min/mo | 600 min/mo | 3,000 min/mo | Unlimited |
| **AI Extraction** | 5/mo | 50/mo | Unlimited | Unlimited |
| **AI Chat** | 10 msg/mo | 100/mo | Unlimited | Unlimited |
| **Storage** | 500 MB | 5 GB | 50 GB | Custom |
| **Export formats** | PDF only | PDF, DOCX, JSON | All + branded | All + branded |
| **Attendees/MOM** | 5 | 20 | Unlimited | Unlimited |
| **Templates** | 3 | 20 | Unlimited | Unlimited |
| **Analytics** | Basic | Full | Full + export | Full + API |
| **AI Provider** | Platform default | Platform default | Choose provider | BYO keys + Ollama |
| **Guest Access** | No | Yes | Yes + password | Yes + SSO |
| **Action Items** | Basic | Full + alerts | Full + escalation | Full + API |
| **Meeting Series** | No | Yes | Yes | Yes |
| **Version History** | Last 3 | Last 10 | Unlimited | Unlimited |
| **Collaboration** | View only | Comments | Full | Full + audit |
| **API Access** | No | No | Read-only | Full CRUD |
| **Branding** | No | No | Logo + colors | Full white-label |
| **Support** | Community | Email | Priority | Dedicated + SLA |
| **Self-hosted** | No | No | No | Yes |
| **SSO / 2FA** | No | No | 2FA | SSO + 2FA |
| **Audit Log** | No | No | Basic | Full |

### Usage Limit Enforcement

```
Limits tracked per organization:
├── moms_count_this_month
├── transcription_minutes_this_month
├── ai_extractions_this_month
├── ai_chat_messages_this_month
├── storage_bytes_used
└── active_users_count

Enforcement:
├── Soft limit  → Warning banner at 80% usage
├── Hard limit  → Block action + upgrade prompt
└── Grace period → 3 days after limit hit (Pro/Business only)
```

### Self-Hosted Enterprise

| Aspect | Detail |
|--------|--------|
| Delivery | Docker image + docker-compose.yml |
| License | Annual flat fee (not per-user) |
| License validation | Offline JWT-based license key |
| Updates | Self-serve via `php artisan antaraflow:update` |
| AI | BYO API keys OR full offline with Ollama |
| Support | Dedicated channel + deployment assistance |

---

## 7. UX/UI Direction & Page Map

### Design Philosophy

| Principle | Application |
|-----------|------------|
| Progressive Disclosure | Simple default view, power features revealed on demand |
| AI-First, Human-Final | AI generates, user reviews & edits. AI never auto-publish |
| Minimal Clicks | Create MOM → Upload → Done. Max 3 clicks to core action |
| Keyboard-First | Cmd+K command palette, shortcuts for power users |
| Mobile-Ready | Record on phone, review on desktop |
| Dark Mode | Full dark mode support from day 1 |

### Visual Direction

```
Style:     Clean, professional, slightly warm
Fonts:     Inter (body) + JetBrains Mono (transcription/code)

Colors:
  Primary    → Deep blue (#1e40af) — trust, professional
  Secondary  → Warm amber (#f59e0b) — energy, AI highlights
  Success    → Green (#059669)
  Warning    → Orange (#ea580c)
  Error      → Red (#dc2626)
  Surface    → White (#ffffff) / Dark (#0f172a)
  AI accent  → Gradient blue-to-purple — identify AI-generated content

Visual Cues:
  ✨ Sparkle icon    → AI-generated content
  🔵 Blue border    → AI suggestion (editable)
  ✅ Solid border   → User-confirmed content
```

### Information Architecture — Page Map

```
antaraFLOW
│
├── AUTH
│   ├── /login
│   ├── /register
│   ├── /forgot-password
│   ├── /accept-invite/{token}
│   └── /guest/{token}
│
├── DASHBOARD
│   └── /dashboard
│       ├── Recent MOMs widget
│       ├── Pending Action Items widget
│       ├── Upcoming Meetings widget
│       ├── Quick Stats (this month)
│       └── AI Briefing card
│
├── MEETINGS (Core MOM)
│   ├── /meetings                    — List all MOMs
│   ├── /meetings/create             — New MOM wizard
│   │   ├── Step 1: Method (record / upload / template / blank)
│   │   ├── Step 2: Details (title, date, project, series)
│   │   ├── Step 3: Attendees (add/invite)
│   │   └── Step 4: Input (record / upload / write)
│   ├── /meetings/{id}              — MOM detail view
│   │   ├── Tab: Summary
│   │   ├── Tab: Transcript
│   │   ├── Tab: Action Items
│   │   ├── Tab: Decisions
│   │   ├── Tab: Attendees
│   │   ├── Tab: Inputs
│   │   ├── Tab: Comments
│   │   ├── Tab: History
│   │   ├── Sidebar: AI Chat (slide-out)
│   │   └── Actions: Export, Share, Finalize, Approve
│   ├── /meetings/{id}/edit
│   ├── /meetings/{id}/export/pdf
│   └── /meetings/{id}/present       — Presentation mode
│
├── ACTION ITEMS
│   ├── /action-items                — Cross-meeting dashboard
│   │   ├── Filter: status, assignee, priority, meeting, overdue
│   │   ├── View: List / Kanban
│   │   └── Bulk actions
│   └── /action-items/{id}
│
├── TEMPLATES
│   ├── /templates
│   ├── /templates/create
│   └── /templates/{id}/edit
│
├── SERIES
│   ├── /series
│   ├── /series/create
│   └── /series/{id}
│
├── ANALYTICS
│   ├── /analytics
│   │   ├── Meeting frequency chart
│   │   ├── Action item completion rate
│   │   ├── Duration trends
│   │   ├── Top topics word cloud
│   │   ├── Participation heatmap
│   │   └── Meeting cost summary
│   └── /analytics/export
│
├── AI SEARCH
│   └── /search
│       ├── Natural language query
│       ├── Results grouped by meeting
│       └── AI-powered answers with source references
│
├── SETTINGS
│   ├── /settings/profile
│   ├── /settings/organization
│   ├── /settings/members
│   ├── /settings/subscription
│   ├── /settings/ai
│   ├── /settings/templates
│   ├── /settings/notifications
│   ├── /settings/integrations
│   ├── /settings/data
│   ├── /settings/security
│   └── /settings/api-keys
│
├── PUBLIC / GUEST
│   ├── /guest/{token}
│   ├── /join/{code}
│   └── /rsvp/{token}
│
└── API (v1)
    ├── /api/v1/meetings
    ├── /api/v1/meetings/{id}/action-items
    ├── /api/v1/meetings/{id}/attendees
    ├── /api/v1/meetings/{id}/export
    ├── /api/v1/action-items
    ├── /api/v1/analytics
    ├── /api/v1/search
    └── /api/v1/webhooks
```

### Key Page Layouts

#### MOM Detail Page — Primary View

```
┌─────────────────────────────────────────────────────┐
│  ← Back    Meeting Title              [Share] [Export]│
│  📅 28 Feb 2026  ⏱ 45 min  👥 8 attendees           │
│  Status: ● Draft    Series: Weekly Standup           │
├────────────────────────────────────────────┬────────┤
│                                            │        │
│  [Summary] [Transcript] [Actions] [Decisions] ...   │
│                                            │  AI    │
│  ┌─ AI Generated Summary ──────────────┐  │  Chat  │
│  │ ✨ Meeting discussed Q1 targets...  │  │        │
│  │    [Edit] [Regenerate] [👍👎]       │  │  Ask   │
│  └─────────────────────────────────────┘  │  about │
│                                            │  this  │
│  ┌─ Key Decisions ─────────────────────┐  │  meet- │
│  │ 1. Approved budget for Phase 2      │  │  ing.. │
│  │ 2. Deadline moved to March 15       │  │        │
│  └─────────────────────────────────────┘  │  [Send]│
│                                            │        │
│  ┌─ Action Items ──────────────────────┐  │        │
│  │ ☐ HIGH  Prepare proposal — @Ali     │  │        │
│  │ ☐ MED   Update timeline — @Sarah    │  │        │
│  │ ☑ LOW   Send notes — @Ahmad         │  │        │
│  └─────────────────────────────────────┘  │        │
│                                            │        │
├────────────────────────────────────────────┴────────┤
│  💬 3 Comments    📎 2 Attachments    📋 v3         │
└─────────────────────────────────────────────────────┘
```

#### MOM Creation Wizard

```
┌─────────────────────────────────────────────┐
│           Create New Meeting                 │
│                                              │
│  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐   │
│  │  🎙  │  │  📁  │  │  📋  │  │  ✏️  │   │
│  │Record│  │Upload│  │Templ.│  │Blank │   │
│  └──────┘  └──────┘  └──────┘  └──────┘   │
│                                              │
│  ● Step 1    ○ Step 2    ○ Step 3           │
│  Method      Details     Attendees           │
│                                              │
│  [Cancel]                    [Next →]        │
└─────────────────────────────────────────────┘
```

#### Action Items Dashboard

```
┌─────────────────────────────────────────────────────┐
│  Action Items                    [List] [Kanban]     │
├──────────┬──────────┬──────────┬──────────┐         │
│ Pending  │In Progress│  Done   │Cancelled │         │
│   (12)   │   (5)    │  (28)   │   (2)    │         │
├──────────┴──────────┴──────────┴──────────┘         │
│                                                      │
│  Filter: [All meetings ▾] [All assignees ▾] [Overdue]│
│                                                      │
│  ● HIGH  Prepare Q1 report     @Ali    Due: Mar 1   │
│  ● HIGH  Client presentation   @Sarah  Due: Mar 3   │
│  ● MED   Update docs           @Ahmad  ⚠ OVERDUE    │
│  ● MED   Review budget         @Lisa   Due: Mar 5   │
│  ● LOW   Archive old files     @Ali    Due: Mar 10  │
└─────────────────────────────────────────────────────┘
```

#### Analytics Dashboard

```
┌─────────────────────────────────────────────────────┐
│  Analytics                    📅 Last 30 days ▾      │
├─────────────────────┬───────────────────────────────┤
│  Meetings This Month│  Action Item Completion        │
│       ┌───┐         │        ╭──────╮               │
│    12 │███│         │       ╱  78%   ╲              │
│     8 │███│██       │      ╱ Complete  ╲            │
│     4 │███│██│██    │      ╰──────────╯             │
│       └───┴──┴──    │                               │
│       W1  W2  W3    │  Done: 28  Pending: 8         │
├─────────────────────┼───────────────────────────────┤
│  Avg Duration       │  Top Topics                    │
│  ⏱ 42 min          │  ████████ Budget (8)           │
│  ↓ 12% from last mo│  ██████   Timeline (6)         │
│                     │  █████    Hiring (5)           │
│                     │  ███      Q1 Goals (3)         │
├─────────────────────┴───────────────────────────────┤
│  Participation Heatmap        Meeting Cost           │
│  Ali    ████████████ 95%      Total: $4,200          │
│  Sarah  ██████████   80%      Avg/meeting: $350      │
│  Ahmad  ████████     65%      Avg/person: $44        │
│  Lisa   ██████       50%                             │
└─────────────────────────────────────────────────────┘
```

#### Command Palette (Cmd+K)

```
┌─────────────────────────────────────┐
│  🔍 Type a command or search...     │
├─────────────────────────────────────┤
│  Recent                             │
│   📄 Weekly Standup — 28 Feb        │
│   📄 Client Review — 25 Feb        │
│                                     │
│  Quick Actions                      │
│   ➕ New Meeting                    │
│   🎙 Start Recording               │
│   📁 Upload Audio                   │
│   🔍 Search All Meetings            │
│                                     │
│  Navigation                         │
│   📊 Analytics                      │
│   ☑ Action Items                    │
│   ⚙ Settings                       │
└─────────────────────────────────────┘
```

### Navigation Structure

```
Sidebar (collapsible):
├── 🏠 Dashboard
├── 📄 Meetings          (badge: 3 draft)
├── ☑  Action Items      (badge: 5 overdue)
├── 📋 Templates
├── 🔄 Series
├── 📊 Analytics
├── 🔍 AI Search
└── ⚙  Settings

Top bar:
├── 🔍 Quick search (Cmd+K trigger)
├── ➕ New Meeting (primary CTA)
├── 🔔 Notifications
└── 👤 Profile menu
```

### Mobile Considerations

| Screen | Mobile Adaptation |
|--------|------------------|
| MOM List | Card view instead of table |
| MOM Detail | Tabs become swipeable sections, AI chat becomes bottom sheet |
| Recording | Full-screen recording UI with large stop button |
| Action Items | Swipe-to-complete gesture |
| Analytics | Single column, scrollable charts |
| Command Palette | Full-screen overlay |

### Real-time Features (WebSocket)

| Feature | Trigger |
|---------|---------|
| Transcription progress | Queue position update, processing percentage |
| AI generation status | "Generating summary...", "Extracting actions..." |
| Collaboration | New comment, mention notification |
| Presence | Who's viewing this MOM right now |
| Action item updates | Status change by team member |

---

## 8. Database Schema

### Overview

| Domain | Tables |
|--------|--------|
| Account & Tenancy | 7 |
| Core MOM | 5 |
| Transcription | 2 |
| Multi-Input | 2 |
| AI Extraction & Chat | 5 |
| Action Items | 2 |
| Attendees | 3 |
| Collaboration | 5 |
| Export | 3 |
| **Total** | **34 tables** |

---

### Account & Tenancy Tables

#### organizations

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| name | string | |
| slug | string | Unique, for subdomain |
| logo_path | string, nullable | |
| brand_colors | JSON | |
| settings | JSON | Defaults, retention, timezone |
| subscription_tier | enum | free/pro/business/enterprise |
| created_at / updated_at / deleted_at | timestamps | Soft delete |

#### users

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK → organizations | |
| name | string | |
| email | string | Unique |
| password | string | |
| avatar_path | string, nullable | |
| role | enum | owner/admin/manager/member/viewer |
| timezone | string | |
| notification_preferences | JSON | |
| two_factor_secret | string, nullable | |
| two_factor_confirmed_at | datetime, nullable | |
| last_login_at | datetime, nullable | |
| created_at / updated_at / deleted_at | timestamps | Soft delete |

#### subscription_plans

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| name | string | |
| tier | enum | free/pro/business/enterprise |
| price_monthly / price_yearly | decimal | |
| limits | JSON | moms, minutes, ai_calls, storage, users |
| features | JSON | Boolean feature flags |
| is_active | boolean | |

#### organization_subscriptions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| plan_id | FK | |
| status | enum | active/past_due/cancelled/trialing |
| trial_ends_at | datetime, nullable | |
| current_period_start / current_period_end | datetime | |
| payment_provider / payment_id | string, nullable | |

#### usage_trackings

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| metric | enum | moms/transcription_minutes/ai_extractions/ai_chat/storage_bytes |
| value | bigint | |
| period | date | First of month |
| | INDEX | UNIQUE(organization_id, metric, period) |

#### api_keys

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| name | string | |
| key_hash | string | Hashed, never plaintext |
| permissions | JSON | |
| last_used_at / expires_at | datetime, nullable | |

#### audit_logs

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| user_id | FK, nullable | |
| action | string | e.g. mom.created, member.invited |
| auditable_type / auditable_id | polymorphic | |
| old_values / new_values | JSON | |
| ip_address | string | |
| user_agent | string | |
| created_at | timestamp | |

---

### Core MOM Tables

#### minutes_of_meetings

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| series_id | FK → meeting_series, nullable | |
| template_id | FK → meeting_templates, nullable | |
| created_by | FK → users | |
| finalized_by / approved_by | FK → users, nullable | |
| title | string | |
| meeting_date | datetime | |
| location | string, nullable | Room, link, or address |
| duration_minutes | int, nullable | |
| status | enum | draft/finalized/approved/archived |
| summary | longtext, nullable | AI or manual |
| decisions | longtext, nullable | |
| notes | longtext, nullable | |
| is_client_visible | boolean | Default false |
| metadata | JSON | Topics, sentiment, speaker stats |
| finalized_at / approved_at | datetime, nullable | |
| created_at / updated_at / deleted_at | timestamps | |
| | INDEX | (organization_id, status), (organization_id, meeting_date) |
| | FULLTEXT | (title, summary, decisions, notes) |

#### meeting_series

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| title | string | |
| description | string, nullable | |
| recurrence_rule | string | RRULE format |
| template_id | FK, nullable | |
| default_attendees | JSON | user_ids + externals |
| next_occurrence | datetime | |
| is_active | boolean | |

#### meeting_templates

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| name | string | |
| description | string, nullable | |
| agenda_structure | JSON | Ordered sections with prompts |
| default_duration_minutes | int | |
| default_attendee_roles | JSON | |
| extraction_config | JSON | What to extract, custom prompts |
| is_default | boolean | |
| usage_count | int | |

#### mom_versions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| version_number | int | UNIQUE with mom_id |
| snapshot | JSON | Full MOM state |
| change_summary | string, nullable | |
| created_by | FK → users | |

#### mom_tags + mom_tag_assignments (pivot)

| Column | Type | Notes |
|--------|------|-------|
| mom_tags.id | bigint | Primary key |
| mom_tags.organization_id | FK | |
| mom_tags.name | string | UNIQUE with org_id |
| mom_tags.color | string | Hex color |
| mom_tag_assignments.mom_id | FK | Pivot |
| mom_tag_assignments.tag_id | FK | Pivot |

---

### Transcription Tables

#### audio_transcriptions

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| mom_id | FK | |
| input_id | FK → mom_inputs | |
| provider | enum | openai_whisper/google_stt/local_whisper |
| model | string | |
| file_path | string | |
| file_size_bytes / duration_seconds | bigint | |
| file_format / language | string | |
| status | enum | pending/queued/processing/completed/failed |
| queue_position | int, nullable | |
| estimated_completion | datetime, nullable | |
| overall_confidence | decimal(3,2) | 0-1 |
| retry_count | int | Default 0 |
| error_message | string, nullable | |
| cost_credits | decimal | AI cost tracking |
| started_at / completed_at | datetime, nullable | |

#### transcription_segments

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| transcription_id | FK | |
| speaker_label | string | |
| speaker_user_id | FK → users, nullable | Mapped speaker |
| text | text | |
| start_time / end_time | decimal | Seconds |
| confidence | decimal(3,2) | 0-1 |
| is_edited | boolean | Default false |
| original_text | text, nullable | Before user edit |

---

### Multi-Input Tables

#### mom_inputs

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| mom_id | FK | |
| type | enum | audio/document/manual_note/video/platform_import |
| file_path / file_name | string, nullable | |
| file_size_bytes | bigint, nullable | |
| mime_type | string, nullable | |
| status | enum | pending/processing/completed/failed |
| processed_text | longtext, nullable | Extracted text |
| metadata | JSON | Page count, duration, source |
| processing_error | string, nullable | |
| sort_order | int | |

#### mom_manual_notes

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| input_id | FK → mom_inputs | |
| content | longtext | Rich text |
| created_by | FK → users | |

---

### AI Extraction & Chat Tables

#### mom_extractions

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| mom_id | FK | |
| type | enum | summary/action_items/decisions/topics/questions/sentiment |
| provider / model | string | |
| prompt_template | text | Prompt used |
| raw_response | longtext | Raw AI response |
| processed_data | JSON | Structured output |
| tokens_input / tokens_output | int | |
| cost_credits | decimal | |
| feedback_score | int, nullable | -1, 0, +1 |
| feedback_note | string, nullable | |
| is_accepted | boolean | Default true |

#### mom_topics

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| extraction_id | FK, nullable | |
| name | string | |
| relevance_score | decimal(3,2) | 0-1 |
| segment_refs | JSON | Array of segment_ids |

#### mom_ai_conversations

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK, nullable | Null for cross-meeting search |
| organization_id | FK | |
| user_id | FK | |
| role | enum | user/assistant/system |
| content | longtext | |
| provider / model | string | |
| tokens_used | int | |
| context_refs | JSON | mom_ids, segment_ids referenced |

#### ai_provider_configs

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| provider | enum | openai/anthropic/google/ollama |
| api_key_encrypted | text, nullable | AES-256 |
| base_url | string, nullable | For Ollama custom endpoint |
| default_model | string | |
| is_default | boolean | |
| settings | JSON | Temperature, max_tokens, etc. |
| | UNIQUE | (organization_id, provider) |

---

### Action Item Tables

#### action_items

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| mom_id | FK | |
| extraction_id | FK, nullable | If AI-generated |
| parent_id | FK → self, nullable | Carried forward from |
| title | string | |
| description | text, nullable | |
| assignee_id | FK → users, nullable | |
| assignee_name | string, nullable | For external assignees |
| priority | enum | high/medium/low |
| status | enum | pending/in_progress/done/cancelled |
| source | enum | ai_extracted/manual |
| due_date | date, nullable | |
| completed_at | datetime, nullable | |
| sort_order | int | |
| | INDEX | (organization_id, status, due_date), (assignee_id, status) |

#### action_item_histories

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| action_item_id | FK | |
| field | string | status, assignee, priority, due_date |
| old_value / new_value | string | |
| changed_by | FK → users | |

---

### Attendee Tables

#### mom_attendees

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| user_id | FK, nullable | Null for externals |
| name / email | string | |
| role | enum | chairperson/secretary/speaker/participant/observer |
| source | enum | internal/external/qr_registration |
| rsvp_status | enum | pending/accepted/declined/tentative |
| is_present | boolean, nullable | |
| notes | string, nullable | |

#### attendee_groups

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| name / description | string | |
| members | JSON | [{user_id, name, email, role}] |

#### mom_join_settings

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | UNIQUE |
| is_enabled | boolean | |
| join_code | string | Unique, for QR/link |
| qr_code_path | string, nullable | |
| allow_external | boolean | Default false |
| requires_approval | boolean | Default false |
| expires_at | datetime, nullable | |

---

### Collaboration Tables

#### mom_comments

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| user_id | FK | |
| parent_id | FK → self, nullable | Threaded |
| content | text | |
| section_ref | string, nullable | "summary", "action_item:123" |
| is_resolved | boolean | Default false |

#### mom_mentions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| comment_id | FK | |
| user_id | FK | |
| is_read | boolean | Default false |

#### mom_reactions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| user_id | FK | |
| section_ref | string | |
| emoji | string | |
| | UNIQUE | (mom_id, user_id, section_ref, emoji) |

#### mom_guest_accesses

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| token | string | UNIQUE (UUID) |
| created_by | FK → users | |
| permissions | JSON | [view, comment, download] |
| password_hash | string, nullable | |
| is_active | boolean | Default true |
| max_views / view_count | int | |
| expires_at / last_accessed_at | datetime, nullable | |

#### notifications

| Column | Type | Notes |
|--------|------|-------|
| id | ULID | Primary key |
| organization_id | FK | |
| user_id | FK | |
| type | string | mom.finalized, action_item.overdue, mention |
| notifiable_type / notifiable_id | polymorphic | |
| title / body | string | |
| action_url | string, nullable | |
| read_at | datetime, nullable | |

---

### Export Tables

#### mom_exports

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| user_id | FK | |
| format | enum | pdf/docx/json/csv |
| template_name | string, nullable | |
| file_path | string | |
| file_size_bytes | bigint | |

#### mom_email_distributions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| mom_id | FK | |
| sent_by | FK → users | |
| recipients | JSON | [{email, name, type}] |
| subject / message | string | |
| export_id | FK → mom_exports, nullable | |
| sent_at | datetime | |

#### export_templates

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | Primary key |
| organization_id | FK | |
| name | string | |
| format | enum | pdf/docx |
| layout | JSON | Sections, styling, logo placement |
| is_default | boolean | |

---

### Entity Relationship Summary

```
Organization ──┬── has many ── Users
               ├── has many ── MinutesOfMeetings
               ├── has many ── MeetingSeries
               ├── has many ── MeetingTemplates
               ├── has many ── ActionItems
               ├── has many ── AttendeeGroups
               ├── has many ── MomTags
               ├── has many ── AIProviderConfigs
               ├── has many ── AuditLogs
               ├── has one ─── OrganizationSubscription
               └── has many ── UsageTrackings

MinutesOfMeeting ──┬── belongs to ── Organization
                   ├── belongs to ── User (created_by)
                   ├── belongs to ── MeetingSeries (optional)
                   ├── belongs to ── MeetingTemplate (optional)
                   ├── has many ──── MomInputs
                   ├── has many ──── AudioTranscriptions
                   ├── has many ──── MomExtractions
                   ├── has many ──── ActionItems
                   ├── has many ──── MomAttendees
                   ├── has many ──── MomComments
                   ├── has many ──── MomReactions
                   ├── has many ──── MomAiConversations
                   ├── has many ──── MomVersions
                   ├── has many ──── MomGuestAccesses
                   ├── has many ──── MomExports
                   ├── has one ───── MomJoinSetting
                   └── many-to-many ─ MomTags (pivot)

AudioTranscription ── has many ── TranscriptionSegments
ActionItem ── has many ── ActionItemHistories
MomComment ── has many ── MomMentions (children via parent_id)
```

---

## 9. Phased Delivery Roadmap

### Phase 1 — MVP Core (Weeks 1-8)

**Goal:** Functional MOM platform with AI transcription and extraction.

| Week | Focus | Deliverables |
|------|-------|-------------|
| 1-2 | Foundation | Laravel project setup, domain structure, auth, multi-tenancy, database migrations |
| 3-4 | Core MOM | CRUD, status workflow, templates, series, version history |
| 5-6 | AI Engine | Multi-provider architecture, transcription pipeline, extraction service |
| 7 | Action Items + Attendees | Full action item management, attendee system |
| 8 | Polish + Testing | Bug fixes, Pest tests, performance optimization |

**MVP Features:** A1-A6, A8, A10, B1-B2, B4-B5, B8-B11, C1-C4, C10, D1-D3, D7-D8, E1, E7, F1-F4, F7-F8, F11, G1-G5, G9, K1-K6, K10

### Phase 2 — Collaboration + Export (Weeks 9-12)

**Goal:** Complete collaboration features and professional export/sharing.

| Week | Focus | Deliverables |
|------|-------|-------------|
| 9 | Collaboration | Comments, mentions, reactions, guest access |
| 10 | Export & Sharing | PDF/DOCX/JSON export, email distribution, branded templates |
| 11 | AI Copilot | AI chat, cross-meeting search, suggested prompts |
| 12 | Analytics | Dashboard, charts, action item completion rates |

**Phase 2 Features:** H1-H2, H5-H6, H8, I1-I5, E1-E2, E7-E8, J1-J3

### Phase 3 — Growth Features (Weeks 13-16)

**Goal:** Pricing tiers, advanced AI, self-hosted preparation.

| Week | Focus | Deliverables |
|------|-------|-------------|
| 13 | Subscription System | Tier enforcement, usage tracking, billing integration |
| 14 | Advanced AI | Sentiment analysis, speaker stats, AI coach, agenda generator |
| 15 | Advanced Features | QR registration, attendee groups, inline annotations, kanban view |
| 16 | Self-hosted Package | Docker setup, install wizard, offline AI (Ollama), license system |

**Phase 3 Features:** K4, K7-K9, B3, B6-B7, B12, C5-C9, E3-E6, G6-G8, H3-H4, I6-I9, J4-J10

### Phase 4 — Integrations & Scale (Weeks 17-20)

**Goal:** Third-party integrations and enterprise features.

| Week | Focus | Deliverables |
|------|-------|-------------|
| 17-18 | Integrations | Zoom, Google Meet, Teams, Slack, calendar sync |
| 19 | API & Webhooks | Public API v1, webhook system, Zapier/Make support |
| 20 | Enterprise | SSO/SAML, advanced audit, white-label, SLA support |

---

## 10. Appendix

### A. Enum Definitions

```
MomStatus: draft, finalized, approved, archived
ActionItemPriority: high, medium, low
ActionItemStatus: pending, in_progress, done, cancelled
ActionItemSource: ai_extracted, manual
MomInputType: audio, document, manual_note, video, platform_import
MomInputStatus: pending, processing, completed, failed
TranscriptionStatus: pending, queued, processing, completed, failed
TranscriptionProvider: openai_whisper, google_stt, local_whisper
AIProvider: openai, anthropic, google, ollama
ExtractionType: summary, action_items, decisions, topics, questions, sentiment
AttendeeRole: chairperson, secretary, speaker, participant, observer
AttendeeSource: internal, external, qr_registration
RSVPStatus: pending, accepted, declined, tentative
UserRole: owner, admin, manager, member, viewer
SubscriptionTier: free, pro, business, enterprise
SubscriptionStatus: active, past_due, cancelled, trialing
ExportFormat: pdf, docx, json, csv
UsageMetric: moms, transcription_minutes, ai_extractions, ai_chat, storage_bytes
ConversationRole: user, assistant, system
```

### B. API Endpoint Summary (v1)

```
# Meetings
GET    /api/v1/meetings
POST   /api/v1/meetings
GET    /api/v1/meetings/{id}
PUT    /api/v1/meetings/{id}
DELETE /api/v1/meetings/{id}
POST   /api/v1/meetings/{id}/finalize
POST   /api/v1/meetings/{id}/approve

# Inputs
POST   /api/v1/meetings/{id}/inputs
GET    /api/v1/meetings/{id}/inputs
DELETE /api/v1/meetings/{id}/inputs/{inputId}

# Transcription
POST   /api/v1/meetings/{id}/transcribe
GET    /api/v1/meetings/{id}/transcription
PUT    /api/v1/meetings/{id}/transcription/segments/{segmentId}

# AI Extraction
POST   /api/v1/meetings/{id}/extract
GET    /api/v1/meetings/{id}/extractions
POST   /api/v1/meetings/{id}/extractions/{extractionId}/feedback

# Action Items
GET    /api/v1/action-items
GET    /api/v1/meetings/{id}/action-items
POST   /api/v1/meetings/{id}/action-items
PUT    /api/v1/action-items/{id}
DELETE /api/v1/action-items/{id}

# Attendees
GET    /api/v1/meetings/{id}/attendees
POST   /api/v1/meetings/{id}/attendees
PUT    /api/v1/meetings/{id}/attendees/{attendeeId}
DELETE /api/v1/meetings/{id}/attendees/{attendeeId}

# AI Chat
POST   /api/v1/meetings/{id}/chat
GET    /api/v1/meetings/{id}/chat/history
POST   /api/v1/search

# Export
GET    /api/v1/meetings/{id}/export/{format}
POST   /api/v1/meetings/{id}/distribute

# Analytics
GET    /api/v1/analytics/meetings
GET    /api/v1/analytics/action-items
GET    /api/v1/analytics/participation

# Webhooks
GET    /api/v1/webhooks
POST   /api/v1/webhooks
DELETE /api/v1/webhooks/{id}
```

### C. Technology References

- Laravel 12 — https://laravel.com/docs/12.x
- Alpine.js — https://alpinejs.dev
- Tailwind CSS v4 — https://tailwindcss.com
- OpenAI API — https://platform.openai.com/docs
- Anthropic API — https://docs.anthropic.com
- Ollama — https://ollama.ai
- Pest v4 — https://pestphp.com

---

**Document Version History**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-28 | antara Product Team | Initial design document |

---

*This document is part of the antara* product ecosystem documentation.*
