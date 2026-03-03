# Browser Recording Design

**Date:** 2026-03-03
**Status:** Approved
**Approach:** Native MediaRecorder + Custom Alpine Component (Approach A)

## Problem Statement

Current issues with Browser Recording in antaraFlow:

1. After clicking record, users aren't confident recording is working despite a running timer indicator
2. After pressing stop, audio file sometimes doesn't appear — users have no status feedback (success, error, lost)

## Decision: Approach A — Native MediaRecorder + Alpine.js

Zero external recording dependencies. Uses browser-native MediaRecorder API + Web Audio API for visualization, managed by an Alpine.js state machine component.

**Why this approach:**

- Fits existing stack (Alpine.js used throughout antaraFlow)
- Zero recording library dependencies — browser APIs are mature in 2026
- Full UX control to solve both key issues precisely
- Hybrid upload strategy adapts to recording length
- IndexedDB safety net ensures recordings are never lost

## Section 1: State Machine & Core Architecture

### States

```
idle → requesting_permission → ready → countdown → recording → paused → stopping → processing → uploading → complete → error
```

| State | UI | Trigger |
|-------|-----|---------|
| `idle` | Mic icon + "Start Recording" button | Default |
| `requesting_permission` | "Allow microphone access" guidance text + browser prompt | Click record |
| `ready` | Green mic level preview (user sees mic works before recording) | Permission granted |
| `countdown` | 3... 2... 1... overlay | Click record from ready |
| `recording` | Pulsing red dot + live waveform + running timer + Stop/Pause buttons | Countdown ends |
| `paused` | Waveform frozen + timer paused + "Paused" badge + Resume/Stop buttons | Click pause |
| `stopping` | Spinner on button + "Finalizing..." | Click stop |
| `processing` | "Processing audio..." + progress indicator | Blob encoded |
| `uploading` | Upload progress bar with % | Blob ready |
| `complete` | Checkmark + audio player + "Transcription in progress..." | Upload done |
| `error` | Red alert + error message + Retry button | Any failure |

### Key Transitions

- `idle → ready`: Skip `requesting_permission` if permission already granted (check `navigator.permissions.query`)
- `recording → paused → recording`: Support pause/resume for long meetings
- `error → retry`: Blob preserved, can retry upload without re-recording
- Countdown (3-2-1): Optional toggle, default ON for first-time, remembers user preference

### Data Flow

```
getUserMedia() → MediaStream
    ├── MediaRecorder (recording chunks)
    └── AudioContext → AnalyserNode → Canvas (visualization)

MediaRecorder.onstop → Blob
    ├── IndexedDB (safety backup)
    └── Upload to server → existing TranscriptionController::store()
```

## Section 2: Live Waveform & Visual Feedback

### Waveform Visualization

**Technology:** Web Audio API `AnalyserNode` + Canvas 2D

**During Recording:**

- Real-time waveform (bar-style or wave-style) responding to voice
- `requestAnimationFrame` loop at 60fps
- `fftSize: 2048` for smooth resolution
- Color gradient: green (quiet) → red (loud) for visual mic level feedback

**During Ready state (pre-record):**

- Live mic level preview (smaller, subtle) — user sees mic works BEFORE pressing record

**After Complete:**

- Static waveform for playback (generated from recorded audio data)
- Playback cursor moves during play

### Audio Feedback

- Record start: Subtle beep (low tone, ~200ms)
- Record stop: Different beep (higher tone, ~150ms)
- Error: Distinct alert sound
- All audio feedback can be disabled in settings

### Button States

| State | Button | Style |
|-------|--------|-------|
| `idle` | Start Recording | Blue/primary |
| `ready` | Record | Green, mic level preview visible |
| `countdown` | 3... 2... 1... | Overlay on waveform area |
| `recording` | Pause / Stop | Red pulsing border, waveform active |
| `paused` | Resume / Stop | Amber, waveform frozen |
| `stopping` | Finalizing... | Disabled, spinner |
| `processing` | Processing... | Progress bar |
| `uploading` | Uploading 67%... | Progress bar with % |
| `complete` | Audio player | Success green checkmark |
| `error` | Retry | Red alert |

### Pulsing Recording Indicator

- Red dot with CSS `pulse` animation (1.5s interval)
- Positioned next to timer: `🔴 0:03:25`

## Section 3: Upload Strategy (Hybrid)

### Logic

```
Recording duration < 5 min  →  Upload after stop (simple, fast)
Recording duration ≥ 5 min  →  Progressive chunked upload every 30s
```

### Short Recording (< 5 min)

```
Stop → Encode blob → Save IndexedDB → Upload single file → Done
```

- Simple POST to existing `TranscriptionController::store()`
- File size typically < 5MB for 5 min audio (WebM Opus)

### Long Recording (≥ 5 min)

```
Recording start
    ↓
Every 30s: MediaRecorder.requestData() → chunk blob → upload chunk
    ↓
Stop → final chunk → upload remaining → server merge chunks → Done
```

- `MediaRecorder.start(30000)` — triggers `ondataavailable` every 30 seconds
- Each chunk uploaded to temporary storage endpoint
- Server collects chunks, merges into single file after recording stops
- After stop, 90%+ data already on server — upload remaining < 2 seconds

### New Backend Endpoints (for chunked upload)

```
POST   /meetings/{meeting}/audio-chunks          → store single chunk
POST   /meetings/{meeting}/audio-chunks/finalize  → merge + create AudioTranscription
DELETE /meetings/{meeting}/audio-chunks            → cleanup (cancel recording)
```

### Chunk Storage

- Temp path: `organizations/{org_id}/audio/chunks/{session_id}/`
- Session ID generated client-side (UUID), sent with each chunk
- Auto-cleanup: Laravel scheduled command purges chunks older than 24h

## Section 4: Error Handling & Recovery (IndexedDB Safety Net)

### Principle: Recording NEVER Lost

Whatever happens — page crash, network down, browser close — user can always recover recording.

### IndexedDB Backup Strategy

```
Recording stop → Blob encoded
    ├── Save to IndexedDB FIRST (immediate, local)
    └── Then upload to server

Upload success → Delete from IndexedDB
Upload fail → Blob stays in IndexedDB → Retry later
```

### Recovery Flow (page reload / next visit)

```
Page load → Check IndexedDB for pending recordings
    ↓
Found? → Show banner: "You have an unsaved recording from [timestamp]"
    ↓
User clicks "Recover" → Resume upload
User clicks "Discard" → Delete from IndexedDB
```

### Error Scenarios & User Messaging

| Scenario | User Sees | Recovery |
|----------|-----------|----------|
| Mic permission denied | "Microphone access needed" + settings guide link | Re-request permission button |
| Mic in use by other app | "Microphone busy — close other apps using mic" | Retry button |
| No mic detected | "No microphone found — check your device" | Retry after plug in |
| Recording failed (codec error) | "Recording failed — try again" | Auto-reset to ready state |
| Blob encoding fail | "Audio processing failed" + Retry | Rare, offer re-record |
| Network down during upload | "Upload paused — waiting for connection" | Auto-retry when online (`navigator.onLine` + `online` event) |
| Upload server error (5xx) | "Upload failed" + Retry button | Blob safe in IndexedDB, retry up to 3x with backoff |
| Page crash / browser close | Recovery banner on next visit | IndexedDB blob intact |
| Safari `onstop` bug | Timeout fallback (3s) → use collected chunks | Transparent to user |

### Auto-Retry Logic

```
Upload fail → Wait 2s → Retry
Retry fail  → Wait 5s → Retry
Retry fail  → Wait 10s → Retry
3x fail     → Stop, show "Upload failed" + manual Retry button
              Blob stays in IndexedDB
```

### Browser Compatibility

| Browser | Format | Notes |
|---------|--------|-------|
| Chrome/Edge | `audio/webm;codecs=opus` | Primary target |
| Firefox | `audio/webm;codecs=opus` | Same as Chrome |
| Safari | `audio/mp4` (AAC) | Fallback, Safari 26.2+ may support WebM |

Format detection on init: try `webm;codecs=opus → webm → mp4 → ogg`, use first supported via `MediaRecorder.isTypeSupported()`.

## Section 5: Integration with Existing Backend

### Existing Infrastructure (Already Built)

- `AudioTranscription` model + storage
- `TranscriptionController::store()` — accepts audio file upload
- `ProcessTranscriptionJob` — sends to OpenAI Whisper
- File storage path: `organizations/{org_id}/audio/`

### Short Recording (< 5 min)

Direct upload to existing `TranscriptionController::store()` — no backend changes needed.

### Long Recording (≥ 5 min) — New Components

**AudioChunkController** with endpoints:

- `POST /meetings/{meeting}/audio-chunks` — store single chunk
- `POST /meetings/{meeting}/audio-chunks/finalize` — merge chunks + create AudioTranscription
- `DELETE /meetings/{meeting}/audio-chunks` — cleanup/cancel

### Frontend Component Structure

```
Alpine Component: x-audio-recorder
├── State machine (Alpine reactive data)
├── MediaRecorder wrapper
├── Web Audio API (AnalyserNode → Canvas waveform)
├── IndexedDB manager (backup/recovery)
├── Upload manager (single file / chunked)
└── UI elements:
    ├── Record/Stop/Pause buttons
    ├── Canvas waveform
    ├── Timer display
    ├── Status indicator (pulsing dot, progress bar, etc.)
    └── Recovery banner (for IndexedDB pending recordings)
```

### Blade Integration

```blade
{{-- Replace existing "Coming Soon" placeholder --}}
<x-audio-recorder :meeting="$meeting" />
```

### File Format Handling

Server accepts multiple formats (browser-dependent):

- `.webm` (Chrome/Firefox/Edge)
- `.mp4` (Safari)
- OpenAI Whisper supports both — no server-side conversion needed

## UX Solutions Summary

### Issue 1: "User tak sure recording berfungsi" → Triple Confidence Pattern

| Signal | Timing | Detail |
|--------|--------|--------|
| Button transform | Instant (0ms) | Record button → pulsing red Stop button |
| Live waveform | ~300ms | Canvas waveform responds to voice in real-time |
| Running timer | ~500ms | 0:01, 0:02... ticking every second |
| Audio beep | Instant | Subtle beep sound on record start |

### Issue 2: "Audio tak appear lepas stop" → Guaranteed Delivery Pattern

```
[STOP clicked]
    ↓ (instant)
UI changes: "Finalizing audio..." + spinner
    ↓ (100ms-2s)
Blob encoded → save to IndexedDB (safety net)
    ↓
"Uploading..." + progress bar (45%... 78%... 100%)
    ↓
Success: Audio player appears with waveform + "Transcription in progress..."
Error: Red alert + "Upload failed" + "Retry" button (blob preserved in IndexedDB)
```

## Technology Stack

| Layer | Choice |
|-------|--------|
| Recording | Native `MediaRecorder` API with `isTypeSupported()` format cascade |
| Format | `audio/webm;codecs=opus` primary, `audio/mp4` Safari fallback |
| Visualization | Web Audio API `AnalyserNode` + Canvas 2D |
| State management | Alpine.js explicit state machine |
| Persistence/recovery | IndexedDB for blob storage |
| Upload | Hybrid — single file < 5min, chunked ≥ 5min |
| Backend | Laravel controller + existing Whisper pipeline |
