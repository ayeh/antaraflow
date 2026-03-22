# antaraNote — Desktop Recorder App Design

## Context

AntaraFlow users currently record meetings via browser (MediaRecorder API) or file upload. Both require the user to be on the AntaraFlow web app. A native desktop recorder app — **antaraNote** — will enable background recording from anywhere, with auto-meeting detection, system audio capture, and real-time streaming to AntaraFlow for live transcription.

This design combines Feature #13 (Desktop Recorder) and Feature #14 (Universal Capture Engine) into a single product.

## Tech Stack

- **Framework**: Tauri v2 (Rust backend + WebView frontend)
- **Frontend**: React + Tailwind CSS (inside Tauri WebView popup)
- **Audio**: Rust `cpal` crate (mic) + OS-native APIs (system audio)
- **Screen**: `scap` crate (optional screen capture)
- **Streaming**: `reqwest` (async HTTP client) for chunked upload
- **Local DB**: SQLite via `rusqlite` (settings, offline buffer, history)
- **Encoding**: `opus` crate for audio compression before upload
- **Platforms**: macOS + Windows (V1)
- **Bundle size**: ~8-15 MB

## Architecture

```
┌─────────────────────────────────────────────────────┐
│              antaraNote (Tauri v2)                    │
│                                                      │
│  ┌──────────────┐                                    │
│  │ Meeting      │  Polls every 5s for:               │
│  │ Detector     │  - Process list (zoom, teams, etc) │
│  │ (Rust)       │  - Calendar events (via API)       │
│  └──────┬───────┘                                    │
│         │ "meeting detected" → show notification     │
│         ▼                                            │
│  ┌──────────────┐  ┌───────────┐  ┌──────────────┐  │
│  │ Audio Engine │  │ Screen    │  │ Streaming    │  │
│  │              │  │ Capture   │  │ Client       │  │
│  │ • Mic (cpal) │  │ (scap)   │  │ (reqwest)    │  │
│  │ • System     │  │ Optional  │  │              │  │
│  │   (OS-native)│  │           │  │ chunk→upload │  │
│  └──────────────┘  └───────────┘  └──────────────┘  │
│                                                      │
│  ┌──────────────────────────────────────────────┐    │
│  │        Local Buffer (SQLite)                 │    │
│  │  - Recording sessions & history              │    │
│  │  - Pending chunks (offline recovery)         │    │
│  │  - Settings & API key (encrypted)            │    │
│  └──────────────────────────────────────────────┘    │
│                                                      │
│  ┌──────────────────────────────────────────────┐    │
│  │        System Tray UI (React WebView)        │    │
│  │  - Recording controls                        │    │
│  │  - Device selection                          │    │
│  │  - Live transcript preview (via WebSocket)   │    │
│  │  - Voice note quick-record                   │    │
│  │  - Settings & connection status              │    │
│  └──────────────────────────────────────────────┘    │
└──────────────────┬──────────────────────────────────┘
                   │ HTTPS (chunks every 15-30s)
                   ▼
┌──────────────────────────────────────────────────────┐
│          antaraFLOW (Laravel API)                    │
│                                                      │
│  POST /api/v1/meetings (create meeting)              │
│  POST /meetings/{id}/live/start                      │
│  POST /meetings/{id}/live/{sid}/chunk                │
│  POST /meetings/{id}/live/{sid}/end                  │
│  POST /meetings/{id}/voice-notes (quick memo)        │
│  WS   live-meeting.{sid} (transcript events)         │
└──────────────────────────────────────────────────────┘
```

## Meeting Detector

Runs as a background Rust task, polling every 5 seconds:

### Process Detection
Scans running processes for known meeting apps:

| App | macOS Process | Windows Process |
|-----|--------------|-----------------|
| Zoom | `zoom.us` | `Zoom.exe` |
| Microsoft Teams | `Microsoft Teams` | `Teams.exe`, `ms-teams.exe` |
| Google Meet | Chrome tab detection | Chrome tab detection |
| Webex | `Meeting Center` | `CiscoWebex.exe` |
| Slack Huddle | `Slack` (audio active) | `Slack.exe` |

- macOS: `sysctl` or `NSWorkspace.runningApplications`
- Windows: `tasklist` or `CreateToolhelp32Snapshot`

### Calendar Sync
- Query AntaraFlow API: `GET /api/v1/meetings?date=today`
- Match meetings starting within 5 minutes
- Auto-suggest the matching meeting for recording

### Trigger Behavior
- **Notification**: "Zoom detected — Record this meeting?" with Accept/Dismiss
- **Auto-record**: If user enables "Auto-record" in settings, skip prompt and start immediately
- **Cooldown**: Don't re-prompt for same app within 10 minutes after dismiss

## Audio Engine

### Microphone Capture
- **Crate**: `cpal` (cross-platform audio I/O)
- Enumerate input devices, user selects from dropdown
- Capture PCM audio at 48kHz/16-bit mono
- Real-time audio level meter (RMS calculation)

### System Audio Capture
- **macOS**: `ScreenCaptureKit` via `screencapturekit-rs` or `objc2` bridge
  - Targeted app audio capture (e.g., capture only Zoom output)
  - Requires macOS 13+ and user permission grant
- **Windows**: `WASAPI loopback` via `windows` crate
  - Captures all system audio output
  - No special permissions needed

### Audio Mixing
- Rust mixer combines mic + system audio into single PCM stream
- Configurable mix ratio (default 50/50)
- Apply noise gate on mic to reduce background noise

### Encoding
- `opus` crate for real-time compression
- Output: Opus-encoded WebM container
- ~32kbps for speech (sufficient quality, ~240KB per minute)
- Reduces upload bandwidth by ~10x vs raw PCM

## Streaming Client

### Chunked Upload Flow
```
Audio Engine → PCM buffer (15-30s) → Opus encode → Upload chunk
                                                       │
                                         ┌─────────────┴──────────┐
                                         │ Online?                │
                                         ├── Yes → POST /live/chunk│
                                         └── No  → SQLite buffer  │
                                                    (retry later)  │
```

### Integration with AntaraFlow Live Meeting API
1. **Start**: `POST /meetings/{id}/live/start` → get `session_id`
2. **Stream**: `POST /meetings/{id}/live/{sid}/chunk` every 15-30s with:
   - `audio` (file blob)
   - `chunk_number` (sequential)
   - `start_time`, `end_time` (relative to recording start)
3. **End**: `POST /meetings/{id}/live/{sid}/end` → triggers final transcription + extraction
4. **Listen**: WebSocket `live-meeting.{sid}` for real-time transcript chunks

### Offline Recovery
- Chunks saved to SQLite with status `pending`
- On reconnect, flush pending chunks in order
- If connection lost for >30 minutes, save full recording locally and upload as single file via `/transcriptions` endpoint

### Authentication
- Uses AntaraFlow API key: `af_{prefix}_{secret}`
- Stored encrypted in SQLite using OS keychain (`keyring` crate)
- Bearer token in all HTTP requests

## System Tray UI

### States

**Idle State:**
- Quick record panel with device selectors (mic, system audio, screen toggle)
- Meeting dropdown (fetched from AntaraFlow API)
- "START RECORD" button
- Recent recordings list with status (completed, uploading, failed)
- Connection status indicator

**Recording State:**
- Recording timer (HH:MM:SS)
- Audio level meter (real-time)
- Live transcript preview (last 3-4 lines from WebSocket)
- Pause / Stop / Voice Note buttons

**Voice Note Mode:**
- Quick-tap record button (10s to 5min max)
- Auto-uploads to current meeting's voice notes
- Uses existing `POST /meetings/{id}/voice-notes` endpoint

### Settings Page
- AntaraFlow URL + API key input
- Default audio devices
- Chunk interval (15s / 30s / 60s)
- Auto-record toggle (on/off)
- Auto-start on login toggle
- Global hotkey config (default: Cmd+Shift+R / Ctrl+Shift+R)

## Project Structure

```
antaranote/
├── src-tauri/
│   ├── Cargo.toml
│   ├── src/
│   │   ├── main.rs              # Tauri app entry
│   │   ├── tray.rs              # System tray setup
│   │   ├── commands/            # Tauri IPC commands
│   │   │   ├── mod.rs
│   │   │   ├── recording.rs     # start/stop/pause commands
│   │   │   ├── devices.rs       # enumerate audio devices
│   │   │   ├── meetings.rs      # fetch meetings from API
│   │   │   └── settings.rs      # read/write settings
│   │   ├── audio/
│   │   │   ├── mod.rs
│   │   │   ├── engine.rs        # Audio capture orchestrator
│   │   │   ├── mic.rs           # Microphone capture (cpal)
│   │   │   ├── system_audio.rs  # System audio (OS-specific)
│   │   │   ├── mixer.rs         # Mix mic + system
│   │   │   └── encoder.rs       # Opus encoding
│   │   ├── capture/
│   │   │   ├── mod.rs
│   │   │   └── screen.rs        # Screen capture (scap)
│   │   ├── detector/
│   │   │   ├── mod.rs
│   │   │   ├── process.rs       # Process scanner
│   │   │   └── calendar.rs      # Calendar sync
│   │   ├── streaming/
│   │   │   ├── mod.rs
│   │   │   ├── client.rs        # HTTP chunk uploader
│   │   │   └── buffer.rs        # Offline SQLite buffer
│   │   └── db/
│   │       ├── mod.rs
│   │       └── schema.rs        # SQLite schema & queries
│   ├── platform/
│   │   ├── macos/
│   │   │   └── audio.rs         # ScreenCaptureKit bridge
│   │   └── windows/
│   │       └── audio.rs         # WASAPI loopback
│   └── tauri.conf.json
├── src/                          # React frontend
│   ├── App.tsx
│   ├── components/
│   │   ├── RecordingPanel.tsx
│   │   ├── DeviceSelector.tsx
│   │   ├── MeetingPicker.tsx
│   │   ├── AudioMeter.tsx
│   │   ├── LiveTranscript.tsx
│   │   ├── RecordingHistory.tsx
│   │   ├── VoiceNoteButton.tsx
│   │   └── Settings.tsx
│   ├── hooks/
│   │   ├── useRecording.ts
│   │   ├── useDevices.ts
│   │   └── useWebSocket.ts
│   └── lib/
│       ├── tauri.ts             # Tauri IPC wrappers
│       └── api.ts               # AntaraFlow API client
├── package.json
└── README.md
```

## Key Rust Dependencies

```toml
[dependencies]
tauri = { version = "2", features = ["tray-icon", "notification"] }
cpal = "0.15"                    # Cross-platform audio I/O
opus = "0.3"                     # Opus audio encoding
reqwest = { version = "0.12", features = ["json", "multipart", "rustls-tls"] }
tokio = { version = "1", features = ["full"] }
rusqlite = { version = "0.31", features = ["bundled"] }
serde = { version = "1", features = ["derive"] }
serde_json = "1"
keyring = "2"                    # OS keychain for API key storage
sysinfo = "0.30"                 # Process detection
uuid = { version = "1", features = ["v4"] }

[target.'cfg(target_os = "macos")'.dependencies]
screencapturekit = "0.2"         # macOS ScreenCaptureKit

[target.'cfg(target_os = "windows")'.dependencies]
windows = { version = "0.52", features = ["Win32_Media_Audio"] }
```

## Data Flow: Recording Session

```
1. User clicks "Start Record" (or auto-triggered by Meeting Detector)
   → Frontend sends `start_recording` Tauri command

2. Rust: Create recording session in SQLite
   → POST /meetings/{id}/live/start → get session_id
   → Start audio engine (mic + system audio threads)
   → Start chunk timer (every 15-30s)

3. Every 15-30 seconds:
   → Audio buffer flushed
   → Opus-encode chunk
   → Save to SQLite (pending status)
   → POST /meetings/{id}/live/{sid}/chunk
   → On success: mark chunk as uploaded
   → On failure: keep as pending (retry on reconnect)

4. WebSocket receives transcript chunks:
   → Display in UI live transcript panel

5. User clicks "Stop"
   → Flush final audio buffer
   → Upload last chunk
   → POST /meetings/{id}/live/{sid}/end
   → Update session status in SQLite
   → Show completion notification with celebration FX
```

## Security

- API key stored in OS keychain (macOS Keychain / Windows Credential Manager)
- All HTTP traffic over HTTPS
- Audio files encrypted at rest in temp directory, deleted after upload
- No audio data stored permanently on disk (only SQLite metadata)

## V1 Scope vs Future

### V1 (This Design)
- System tray app with recording controls
- Mic + system audio capture
- Real-time chunked streaming to AntaraFlow
- Meeting auto-detection (process + calendar)
- Voice note quick-record
- Offline buffer with retry
- macOS + Windows
- Global hotkey

### Future (V2+)
- Screen capture with visual timeline
- Speaker diarization at capture time
- Noise cancellation (RNNoise)
- Multiple meeting app audio isolation
- Linux support
- Auto-update (Tauri updater plugin)
- Team deployment via MDM
