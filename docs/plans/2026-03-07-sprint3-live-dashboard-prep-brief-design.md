# Sprint 3: Live Meeting AI Dashboard + AI Meeting Prep Brief

**Date:** 2026-03-07
**Status:** Approved
**Effort:** 10-13 weeks (2 features)
**Priority:** High — "Wow Factor" viral features

---

## Feature 1: Live Meeting AI Dashboard

### Overview

A real-time dashboard active **during live meetings** that shows AI-powered meeting intelligence forming in front of participants' eyes. Three-panel layout: Live Transcript, AI Extractions, and Meeting Controls — all synchronized via WebSockets.

### Approach

**Chunked Transcription + Periodic AI Refresh** — Audio is chunked every 30-60 seconds, transcription processes per chunk, AI extraction re-runs every 5 minutes. Builds on existing chunked upload infrastructure.

### Architecture

```
┌─────────────────────────────────────────────┐
│           LIVE MEETING DASHBOARD            │
├─────────────┬───────────────┬───────────────┤
│  LIVE FEED  │  AI EXTRACT   │   CONTROLS    │
│             │               │               │
│ Transcript  │ ● Decisions   │ ⏱ Timer       │
│ segments    │ ● Actions     │ 👥 Attendance  │
│ streaming   │ ● Key Points  │ 🗳 Voting      │
│ w/ speakers │ ● Topics      │ 📋 Agenda      │
│             │ (auto-update  │    Progress    │
│ [editable]  │  every 5min)  │ 💰 Cost Calc   │
│             │               │               │
│             │ [approve/     │ [mark present] │
│             │  edit/reject] │ [start vote]   │
└─────────────┴───────────────┴───────────────┘
         ↕ WebSocket (Laravel Reverb)
         ↕ All participants see same state
```

### Backend Components

#### New Service
- `LiveMeetingService` — orchestrate live session lifecycle (start, stop, pause, resume)
  - `startSession(MinutesOfMeeting, User): LiveMeetingSession`
  - `endSession(LiveMeetingSession): void` — merge chunks into final transcription
  - `getSessionState(LiveMeetingSession): array` — current transcript + extractions
  - `processChunk(LiveMeetingSession, audioData): void`

#### New Jobs
- `LiveTranscriptionJob` — process individual audio chunks via Whisper API
  - Input: `live_transcript_chunk` record
  - Output: transcribed text + speaker + timestamps → broadcast via Reverb
  - Queue: `live-transcription` (separate queue for priority)
  - Retry: 2 attempts, 30-second backoff

- `LiveExtractionJob` — periodic AI extraction on accumulated transcript
  - Triggered every 5 minutes during active session
  - Aggregates all processed chunks → calls `ExtractionService` methods
  - Broadcasts updated decisions, action items, topics, key points
  - Queue: `live-extraction`

#### New Events (broadcast via Reverb)
- `TranscriptionChunkProcessed` — new transcript segment available
- `LiveExtractionUpdated` — AI extractions refreshed
- `LiveSessionStarted` / `LiveSessionEnded`
- `AttendanceMarked` — attendee presence changed
- `AgendaItemProgressed` — moved to next agenda item
- `LiveVoteStarted` / `LiveVoteCast` / `LiveVoteEnded`

#### New Channel
- `live-meeting.{sessionId}` — private channel for live session events

### Frontend Components

#### `live-meeting-dashboard.js` (Alpine.js)
Three-panel responsive layout:

**Panel 1: Live Transcript Feed**
- Auto-scrolling transcript with speaker labels and timestamps
- Color-coded speakers
- Inline edit capability (click to correct transcription errors)
- "Jump to latest" button when scrolled up
- Search within transcript

**Panel 2: AI Extractions (auto-updating)**
- Decisions list — with approve/edit/reject buttons
- Action items — with assignee, priority, due date (editable)
- Key points / topics — collapsible sections
- Confidence indicators per extraction
- "Create from AI" bulk action button
- Last updated timestamp with loading indicator

**Panel 3: Meeting Controls**
- Agenda progress tracker — visual timeline with current item highlighted
- Per-agenda-item timer (configurable time per item)
- Attendance panel — mark present/absent, show quorum status
- Live voting — start vote, cast vote, show results
- Meeting cost calculator — real-time running cost
- Recording controls — pause/resume/stop

#### Real-Time Sync
- Echo subscription to `live-meeting.{sessionId}` channel
- Presence channel for active viewers count
- Optimistic UI updates with server reconciliation

### Database

#### `live_meeting_sessions` (new table)
```
id                      bigint unsigned PK
minutes_of_meeting_id   bigint unsigned FK → minutes_of_meetings
started_by              bigint unsigned FK → users
status                  varchar(20) — active, paused, ended
config                  json — {chunk_interval: 30, extraction_interval: 300, agenda_items: [...]}
started_at              timestamp
paused_at               timestamp nullable
ended_at                timestamp nullable
total_duration_seconds   int nullable
created_at              timestamp
updated_at              timestamp
```

#### `live_transcript_chunks` (new table)
```
id                      bigint unsigned PK
live_meeting_session_id bigint unsigned FK → live_meeting_sessions
chunk_number            int unsigned
audio_file_path         varchar(255) nullable
text                    text nullable
speaker                 varchar(255) nullable
start_time              double — relative to session start
end_time                double
confidence              double nullable
status                  varchar(20) — pending, processing, completed, failed
error_message           text nullable
created_at              timestamp
updated_at              timestamp
```

### Data Flow

1. Chair clicks "Start Live Meeting" → `LiveMeetingService->startSession()`
2. Browser records audio via existing `audio-recorder.js` (modified for live mode)
3. Every 30s → chunk uploaded to `/live-meeting/{session}/chunk` endpoint
4. `LiveTranscriptionJob` dispatched → Whisper API → `live_transcript_chunks` updated
5. Broadcast `TranscriptionChunkProcessed` → all participants see new text
6. Every 5 minutes → `LiveExtractionJob` dispatched → AI extracts from accumulated text
7. Broadcast `LiveExtractionUpdated` → all participants see decisions/actions/topics
8. Chair clicks "End Meeting" → `LiveMeetingService->endSession()`
9. All chunks merged into final `AudioTranscription` + `TranscriptionSegments`
10. Full `ExtractMeetingDataJob` dispatched for comprehensive final extraction

### Existing Infrastructure Used
- `audio-recorder.js` — chunked recording (30s chunks after 5 min, modify for immediate chunking)
- `ProcessTranscriptionJob` / Whisper integration — transcription pipeline ready
- `ExtractionService` — all AI extraction logic (summary, actions, decisions, topics)
- `meeting-live.js` — Echo/Reverb channels configured
- Board meeting mode — quorum tracking, resolution voting
- Meeting cost calculator — already exists (enhance with real-time display)

### Edge Cases
- Network disconnection mid-meeting → IndexedDB buffer (existing recovery mechanism)
- Whisper API timeout → retry with exponential backoff, show "processing..." indicator
- Multiple recorders (multiple participants) → only Chair/Secretary can record (enforce in UI)
- Browser tab closed → session persists server-side, rejoinable
- Very long meetings (>3 hours) → chunk cleanup after session end, aggregate incrementally

---

## Feature 2: AI Meeting Prep Brief

### Overview

24 hours before each meeting, every attendee receives a **personalized AI-generated intelligence brief** — delivered via email and accessible in-app. Contains action item status, historical context, suggested questions, reading priorities, and conflict of interest flags.

### Approach

**Scheduled Auto-Generate + Email + In-App** — Daily cron job checks for meetings in next 24 hours, generates personalized briefs per attendee, sends email notification, and caches brief for in-app viewing.

### Brief Content Structure

```
1. EXECUTIVE SUMMARY
   AI-generated overview of meeting purpose and expected outcomes

2. YOUR ACTION ITEMS
   ⚠ Overdue items assigned to this attendee
   ✅ Completed since last meeting
   🔄 Carried forward items

3. UNRESOLVED FROM LAST MEETING
   Key decisions pending follow-up
   Items that were deferred

4. AGENDA DEEP-DIVE (per agenda item)
   - Background context (AI summary from historical meetings)
   - Relevant past decisions on this topic
   - Suggested questions to ask
   - Supporting documents (links)

5. KEY METRICS SNAPSHOT
   - Governance health score
   - Action completion rate
   - Attendance trend

6. READING LIST
   Prioritized documents to review
   with estimated reading time
   📄 Financial Report (12 pages, ~15 min)
   📄 Risk Assessment (8 pages, ~10 min)

7. CONFLICTS OF INTEREST FLAG
   ⚠ Agenda Item 3 relates to Company X — you have disclosed interest
```

### Backend Components

#### New Service
- `MeetingPrepBriefService` — extends existing `MeetingPreparationService`
  - `generateForMeeting(MinutesOfMeeting): Collection<MeetingPrepBrief>`
  - `generateForAttendee(MinutesOfMeeting, MomAttendee): MeetingPrepBrief`
  - `getPersonalizedContent(MinutesOfMeeting, MomAttendee): array`

  **Personalization logic:**
  ```
  For each attendee:
  ├── IF role == Chair/Secretary
  │   └── Full management view (all items, all metrics, all conflicts)
  ├── IF role == Director/Member
  │   └── Personal items + agenda context + their conflicts only
  ├── IF has conflict of interest vs agenda topics
  │   └── Flag relevant agenda items with warning
  └── IF new member (joined in last 3 meetings)
      └── Include extra context about past decisions and org history
  ```

  **Data gathered per attendee:**
  - Action items: overdue, pending, completed (via `ActionItem` queries)
  - Last 5 meetings in project/series: summaries, decisions, carried-forward items
  - Documents attached to upcoming meeting: filename, size, estimated page count, reading time
  - Conflict of interest: match attendee affiliations against agenda item topics (AI-assisted)
  - Governance metrics: attendance %, completion rate, meeting frequency

#### New Job
- `GeneratePrepBriefsJob` — scheduled daily at 08:00
  - Scans meetings with `meeting_date` in next 24 hours
  - Filters: status must be Draft or InProgress, must have attendees
  - For each meeting → for each attendee → generate brief
  - Store in `meeting_prep_briefs` table
  - Dispatch `MeetingPrepBriefNotification` per attendee

#### New Notification
- `MeetingPrepBriefNotification` — Laravel notification
  - Channels: `mail`, `database`
  - Mail: concise email with top 3 highlights + "View Full Brief" CTA
  - Database: in-app notification with link to brief page

#### New Controller
- `PrepBriefController`
  - `show(MinutesOfMeeting)` — display personalized brief (auto-filtered by auth user)
  - `generate(MinutesOfMeeting)` — manual trigger for on-demand generation
  - `markViewed(MeetingPrepBrief)` — track engagement

### Frontend Components

#### Prep Brief Page (Blade + Alpine.js)
- Accessible from meeting detail page as "Prep Brief" tab
- Collapsible sections matching the brief structure
- Action items shown with status badges and quick-update buttons
- Documents shown with download links and reading time estimates
- "Mark as Read" tracking per section
- Print-friendly layout
- Mobile-responsive for reading on phone/tablet

#### Email Template
- Clean, minimal design
- Meeting title, date, time, location
- Top 3 things attendee needs to know
- Count of pending action items with urgency level
- Estimated total prep time
- Single "View Full Brief" CTA button
- Unsubscribe link for brief notifications

### Database

#### `meeting_prep_briefs` (new table)
```
id                      bigint unsigned PK
minutes_of_meeting_id   bigint unsigned FK → minutes_of_meetings
attendee_id             bigint unsigned FK → mom_attendees
user_id                 bigint unsigned FK → users (nullable, for external attendees)
content                 json — full structured brief content
summary_highlights      json — top 3 highlights for email
estimated_prep_minutes  int — total estimated preparation time
generated_at            timestamp
email_sent_at           timestamp nullable
viewed_at               timestamp nullable
sections_read           json nullable — track which sections viewed
created_at              timestamp
updated_at              timestamp
```

### Data Flow

1. Daily scheduler (08:00) → `GeneratePrepBriefsJob`
2. Query: `MinutesOfMeeting` where `meeting_date` between now and +24h
3. For each meeting → for each attendee:
   a. Query attendee's action items (overdue, pending, completed since last meeting)
   b. Query last 5 meetings in same project/series (summaries, decisions)
   c. Query documents attached to meeting (size, type, estimate page count)
   d. Call AI to generate: executive summary, suggested questions, reading priorities
   e. Check conflict of interest (AI matches attendee profile vs agenda topics)
   f. Calculate governance metrics (attendance %, completion rate)
4. Store structured brief in `meeting_prep_briefs`
5. Send `MeetingPrepBriefNotification` (email + database)
6. Attendee opens email → clicks "View Full Brief" → in-app page
7. Track `viewed_at` and `sections_read` for engagement analytics

### Existing Infrastructure Used
- `MeetingPreparationService` — already generates suggested agenda, carryover items, discussion topics
- `ExtractionService` — AI summarization ready
- Email notification system — templates and SMTP config exists
- `OverdueActionItemsJob` — scheduler pattern exists
- Action items — full query infrastructure with overdue detection
- Attendee management — role, RSVP, presence tracking
- Documents — `MomDocument` model with file metadata

### Edge Cases
- Meeting with no agenda items → generate brief with action items and metrics only
- External attendees (no user account) → skip personalized action items, include general brief
- Meeting rescheduled after brief sent → detect date change, regenerate and re-send
- AI generation fails → store partial brief with error flag, retry in 2 hours
- Very large number of attendees (>50) → batch AI calls, queue per attendee

---

## Implementation Priority

| Order | Component | Effort | Dependencies |
|-------|-----------|--------|-------------|
| 1 | Database migrations (both features) | 2 days | None |
| 2 | `MeetingPrepBriefService` + `GeneratePrepBriefsJob` | 2 weeks | Migrations |
| 3 | Prep Brief email template + notification | 1 week | Service |
| 4 | Prep Brief in-app page (Blade/Alpine) | 1 week | Service |
| 5 | `LiveMeetingService` + session management | 1 week | Migrations |
| 6 | `LiveTranscriptionJob` + chunk processing | 2 weeks | Service, Whisper |
| 7 | `LiveExtractionJob` + periodic AI | 1 week | Transcription |
| 8 | Live Dashboard frontend (3-panel layout) | 2 weeks | All backend |
| 9 | Real-time sync (Echo/Reverb events) | 1 week | Frontend + backend |
| 10 | Testing + edge cases + polish | 2 weeks | All |

**Total: ~12 weeks** for small team (3-5 devs working in parallel)

---

## Success Metrics

- **Live Dashboard:** Average session duration, % meetings using live mode, transcript accuracy, AI extraction approval rate
- **Prep Brief:** Email open rate, in-app view rate, sections read %, prep time reported by directors, meeting effectiveness scores before/after
