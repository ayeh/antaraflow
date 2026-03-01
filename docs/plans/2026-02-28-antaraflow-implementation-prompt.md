# antaraFLOW — Implementation Planning

## Context
antaraFLOW is a standalone AI-powered Minutes of Meeting (MOM) platform. Part of the antara* product ecosystem. Cloud SaaS + self-hosted option.

## Key Decisions (Already Finalized)
- Product Name: antaraFLOW
- Target: Tiered (Free → Pro → Business → Enterprise)
- Deployment: Cloud SaaS + Self-hosted (Docker + Ollama for offline)
- Starting Point: Build fresh Laravel project (reference antaraPROJECT patterns)
- Frontend: Vanilla JS ES6+ / Alpine.js / Tailwind CSS v4 (NO React/Vue/Livewire)
- AI: Multi-provider from day 1 (OpenAI, Anthropic, Google, Ollama) via interface/adapter pattern
- Architecture: Monolith Laravel, domain-driven internal structure

## Tech Stack
- Laravel 12 (PHP 8.4)
- MySQL 8.0 (MariaDB compatible)
- Vanilla JS + Alpine.js + Tailwind CSS v4
- Vite bundler
- Pest v4 testing
- Laravel Queue (Redis cloud / database self-hosted)
- Laravel Broadcasting (Pusher/Soketi)
- Laravel Scout + Meilisearch (optional)

## Architecture — Domain Structure

```
app/
├── Domain/
│   ├── Meeting/        (MOM CRUD, templates, series, versions)
│   ├── Transcription/  (audio processing, queue, segments)
│   ├── AI/             (multi-provider, extraction, chat, coach)
│   ├── ActionItem/     (tasks, tracking, escalation, history)
│   ├── Attendee/       (RSVP, roles, QR, groups)
│   ├── Collaboration/  (comments, mentions, reactions, guest access)
│   ├── Analytics/      (dashboards, insights, aggregators)
│   ├── Export/         (PDF, DOCX, email distribution)
│   └── Account/        (org, users, subscription, tenancy)
├── Infrastructure/
│   ├── AI/             (AIProviderFactory, adapters)
│   ├── Storage/        (S3/local abstraction)
│   └── Tenancy/        (BelongsToOrganization, global scopes)
└── Support/
    ├── Enums/
    └── Helpers/
```

## Database — 34 Tables

Account & Tenancy (7): organizations, users, subscription_plans, organization_subscriptions, usage_trackings, api_keys, audit_logs

Core MOM (5): minutes_of_meetings, meeting_series, meeting_templates, mom_versions, mom_tags + pivot

Transcription (2): audio_transcriptions, transcription_segments

Multi-Input (2): mom_inputs, mom_manual_notes

AI (5): mom_extractions, mom_topics, mom_ai_conversations, ai_provider_configs

Action Items (2): action_items, action_item_histories

Attendees (3): mom_attendees, attendee_groups, mom_join_settings

Collaboration (5): mom_comments, mom_mentions, mom_reactions, mom_guest_accesses, notifications

Export (3): mom_exports, mom_email_distributions, export_templates

## AI Provider Interface

```php
interface AIProviderInterface {
    public function chat(string $prompt, array $context): string;
    public function summarize(string $text): MeetingSummary;
    public function extractActionItems(string $text): array;
    public function extractDecisions(string $text): array;
}

interface TranscriberInterface {
    public function transcribe(string $filePath, array $options): TranscriptionResult;
    public function supportsDiarization(): bool;
    public function supportedLanguages(): array;
}
```

Implementations: OpenAIProvider, AnthropicProvider, GoogleProvider, OllamaProvider

## MVP Scope — Phase 1 (105 features total, 56 must-have)

Module A — Core MOM: CRUD, status workflow (draft→finalized→approved), templates, series, version history, search

Module B — Transcription: Audio upload, browser recording, speaker diarization, multi-language, queue system, retry, editing

Module C — AI Extraction: Auto-generate summary, action items, decisions, topics, feedback scoring

Module D — Multi-Input: Audio upload, document extraction, manual notes, combine inputs

Module E — AI Copilot: AI chat within MOM, chat history (cross-meeting search Phase 2)

Module F — Action Items: Priority, status tracking, assignee, deadline, overdue alerts, cross-meeting dashboard, carry forward

Module G — Attendees: Internal/external, RSVP, roles, present/absent, bulk invite

Module K — Account: Multi-tenancy, RBAC (owner/admin/manager/member/viewer), org settings, audit logging

Phase 2 (Weeks 9-12): Collaboration (comments, mentions, guest access), Export (PDF/DOCX/JSON, email), Analytics dashboard

Phase 3 (Weeks 13-16): Subscription tiers, advanced AI, self-hosted package, QR registration

Phase 4 (Weeks 17-20): Integrations (Zoom, Slack, Teams), Public API, Enterprise features

## Conventions
- PSR-12 PHP, strict types
- Form Requests for validation
- Policies for authorization
- Service layer for business logic
- Eloquent ORM (no raw SQL)
- Factories + Seeders for all models
- Pest v4 tests (80%+ coverage)
- Laravel Pint for formatting

## Task
Create a detailed Phase 1 implementation plan:
1. Break down into weekly sprints (8 weeks)
2. Each sprint: specific files to create, migrations, models, services, controllers, tests
3. Define migration execution order (dependencies)
4. Define service contracts/interfaces first
5. Include test strategy per module
6. Consider multi-tenancy setup as foundation (Week 1)
7. AI provider architecture as early foundation (Week 1-2)

Start planning. Buat dalam format yang boleh terus execute step by step.
