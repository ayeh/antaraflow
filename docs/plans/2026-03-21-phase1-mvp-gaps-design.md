# Phase 1 MVP Gaps — Design Document

**Date:** 2026-03-21
**Scope:** B2 Browser Recording, B4 Speaker Diarization, B5 Multi-language, E2 Cross-meeting AI Search
**Approach:** Full Featured (C)

---

## Overview

Four features complete the Phase 1 MVP. B2 and B5 are mostly built — only UI gaps remain. B4 requires a heuristic-based speaker detection layer on top of Whisper segments plus a full rename + timeline UI. E2 is a new AI-powered search tab within the existing `/search` page.

---

## B2 + B5 — Browser Recording & Multi-language

### Language Selection UI

**Recorder Modal (`audio-recorder.js` + Blade view):**
- Add `language` Alpine.js state, defaulting to `navigator.language` (auto-detect, fallback `'en'`)
- Language dropdown shown in `ready` state (after permission granted, before countdown)
- Supported languages pulled from `OpenAIWhisperTranscriber::supportedLanguages()`: en, ms, zh, ta, ja, ko, fr, de, es, pt, ar, hi
- Language value sent in chunk `finalize` request payload

**Upload Form (`transcription.blade.php`):**
- Add `<x-language-select name="language">` Blade component to existing upload form
- Same supported language list

### Files Changed
| File | Action |
|------|--------|
| `resources/js/audio-recorder.js` | Add `language` state + dropdown in `ready` state |
| `resources/views/meetings/tabs/transcription.blade.php` | Add language select to upload form |
| `resources/views/components/language-select.blade.php` | **New** reusable language selector component |

---

## B4 — Speaker Diarization (Manual with Heuristic)

### Auto-grouping Logic

In `ProcessTranscriptionJob`, after Whisper returns segments:
- Detect "speaker turn" when time gap between consecutive segments exceeds **1.5 seconds**
- Auto-assign labels: `Speaker 1`, `Speaker 2`, `Speaker 3`... per turn
- Speaker numbering resets per transcription
- Stored in existing `transcription_segments.speaker` column

### Rename UI (Inline Edit)

In `transcriptions/show.blade.php`:
- Speaker badge is clickable → shows inline text input for rename
- Renaming one instance updates **all segments with the same label** in that transcription
- Save via `PATCH /meetings/{meeting}/transcriptions/{transcription}/speakers`

### Speaker Timeline Visual

At the top of the transcription show page:
- Horizontal timeline bar — each speaker gets a distinct colour (from existing 6-colour palette)
- Segments are proportional to `start_time` / `end_time` vs total `duration_seconds`
- Hover on bar segment → tooltip showing speaker name + timestamp range
- Implemented with pure CSS + Alpine.js, no external libraries

### Files Changed
| File | Action |
|------|--------|
| `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php` | Add speaker turn detection algorithm |
| `resources/views/transcriptions/show.blade.php` | Add speaker timeline + inline rename UI |
| `app/Domain/Transcription/Controllers/SpeakerController.php` | **New** — handle bulk speaker rename |
| `routes/web.php` | Add `PATCH meetings/{meeting}/transcriptions/{transcription}/speakers` |

---

## E2 — Cross-meeting AI Search

### UI — AI Tab in `/search`

Extend `search/index.blade.php` with two tabs:
- **"Search"** — existing keyword results (unchanged)
- **"AI Search"** — new tab with:
  - Large textarea: "Tanya soalan tentang mana-mana meeting..."
  - Streaming response panel below
  - Source citations: list of meetings used as context (name + date + link)
  - Alpine.js handles tab switching + streaming display

### Backend — AI Search Flow

```
User query → keyword pre-filter → context assembly → AI prompt → stream response → citations
```

1. **Keyword pre-filter** — `GlobalSearchService` returns top 10 meetings matching query keywords
2. **Context assembly** — per meeting: `title`, `date`, `summary`, `full_text` (transcription), `action_items`, `decisions`
3. **Token budget** — max 1,000 tokens per meeting, max 8,000 tokens total (prevents overflow)
4. **AI prompt** — system prompt defines meeting assistant role, injects context, answers user query
5. **Stream response** — Laravel Server-Sent Events (SSE) streams chunks to browser
6. **Source tracking** — returns `meeting_ids` used in context for citation display

### Caching

- Cache AI responses in Laravel Cache: key `ai_search:{org_id}:{hash(query)}`
- TTL: 1 hour
- Cache invalidated when any meeting in the organisation is updated

### Files Changed
| File | Action |
|------|--------|
| `resources/views/search/index.blade.php` | Add AI Search tab + streaming UI |
| `app/Domain/Search/Controllers/AiSearchController.php` | **New** — handle AI search requests |
| `app/Domain/Search/Services/AiSearchService.php` | **New** — context assembly, AI call, caching |
| `routes/web.php` | Add `POST /search/ai` route |

---

## Implementation Order

1. B5 language component + B2 recorder UI (quick wins, unblock B4 testing)
2. B4 speaker detection logic + SpeakerController
3. B4 speaker timeline + inline rename UI
4. E2 AiSearchService + AiSearchController
5. E2 search UI with AI tab + streaming + citations

---

## Out of Scope

- AssemblyAI / Pyannote automatic diarization
- Vector embeddings / semantic similarity search
- Multi-tenant search across organisations
