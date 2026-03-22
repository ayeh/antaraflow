# antaraNote Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a native desktop recorder app (antaraNote) that captures mic + system audio, streams chunks to AntaraFlow's Live Meeting API in real-time, and auto-detects running meeting apps.

**Architecture:** Tauri v2 app with Rust backend for audio capture/streaming and React frontend for system tray UI. Audio is captured via `cpal` (mic) + OS-native APIs (system audio), Opus-encoded, and uploaded as chunks every 30 seconds. SQLite stores settings, sessions, and offline buffer.

**Tech Stack:** Tauri v2, Rust, React, Tailwind CSS, cpal, opus, reqwest, rusqlite, sysinfo

**Design Doc:** `docs/plans/2026-03-23-antaranote-desktop-recorder-design.md`

---

## Task 1: Scaffold Tauri v2 Project

**Files:**
- Create: `antaranote/` (new directory at repo root, sibling to antaraFlow)

**Step 1: Install Tauri CLI and create project**

```bash
cd /Users/ayeh/Dev/Herd
npm create tauri-app@latest antaranote -- --template react-ts --manager npm
cd antaranote
```

Select: React + TypeScript template.

**Step 2: Verify scaffold builds**

```bash
cd /Users/ayeh/Dev/Herd/antaranote
npm install
npm run tauri dev
```

Expected: Tauri dev window opens with React template.

**Step 3: Install frontend dependencies**

```bash
npm install -D tailwindcss @tailwindcss/vite
npm install lucide-react clsx
```

**Step 4: Configure Tailwind**

Edit `vite.config.ts` to add Tailwind plugin.
Edit `src/styles.css` to add `@import "tailwindcss"`.

**Step 5: Commit**

```bash
git init
git add .
git commit -m "chore: scaffold antaraNote Tauri v2 project with React + Tailwind"
```

---

## Task 2: Add Rust Dependencies to Cargo.toml

**Files:**
- Modify: `antaranote/src-tauri/Cargo.toml`

**Step 1: Add core dependencies**

```toml
[dependencies]
tauri = { version = "2", features = ["tray-icon"] }
tauri-plugin-notification = "2"
tauri-plugin-global-shortcut = "2"
tauri-plugin-autostart = "2"
serde = { version = "1", features = ["derive"] }
serde_json = "1"
tokio = { version = "1", features = ["full"] }
reqwest = { version = "0.12", features = ["json", "multipart", "rustls-tls"] }
rusqlite = { version = "0.31", features = ["bundled"] }
cpal = "0.15"
opus = "0.3"
sysinfo = "0.30"
uuid = { version = "1", features = ["v4"] }
keyring = "2"
chrono = { version = "0.4", features = ["serde"] }
log = "0.4"
env_logger = "0.11"
thiserror = "1"
parking_lot = "0.12"

[target.'cfg(target_os = "macos")'.dependencies]
screencapturekit = "0.2"

[target.'cfg(target_os = "windows")'.dependencies]
windows = { version = "0.52", features = ["Win32_Media_Audio", "Win32_System_Com"] }
```

**Step 2: Verify it compiles**

```bash
cd antaranote && cargo check --manifest-path src-tauri/Cargo.toml
```

Expected: Compiles without errors (warnings OK).

**Step 3: Commit**

```bash
git add src-tauri/Cargo.toml src-tauri/Cargo.lock
git commit -m "chore: add Rust dependencies for audio, streaming, and detection"
```

---

## Task 3: SQLite Database Layer

**Files:**
- Create: `antaranote/src-tauri/src/db/mod.rs`
- Create: `antaranote/src-tauri/src/db/schema.rs`

**Step 1: Create db module**

`src-tauri/src/db/mod.rs`:
```rust
pub mod schema;

use rusqlite::Connection;
use std::path::PathBuf;
use std::sync::Mutex;
use tauri::AppHandle;
use tauri::Manager;

pub struct Database {
    pub conn: Mutex<Connection>,
}

impl Database {
    pub fn new(app_dir: PathBuf) -> Result<Self, rusqlite::Error> {
        std::fs::create_dir_all(&app_dir).ok();
        let db_path = app_dir.join("antaranote.db");
        let conn = Connection::open(db_path)?;
        schema::run_migrations(&conn)?;
        Ok(Self { conn: Mutex::new(conn) })
    }
}

pub fn get_db(app: &AppHandle) -> &Database {
    app.state::<Database>().inner()
}
```

**Step 2: Create schema with migrations**

`src-tauri/src/db/schema.rs`:
```rust
use rusqlite::Connection;

pub fn run_migrations(conn: &Connection) -> Result<(), rusqlite::Error> {
    conn.execute_batch("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS recording_sessions (
            id TEXT PRIMARY KEY,
            meeting_id INTEGER,
            meeting_title TEXT,
            session_id TEXT,
            status TEXT NOT NULL DEFAULT 'recording',
            started_at TEXT NOT NULL,
            ended_at TEXT,
            duration_seconds INTEGER DEFAULT 0,
            total_chunks INTEGER DEFAULT 0,
            uploaded_chunks INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS pending_chunks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_local_id TEXT NOT NULL,
            chunk_number INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            start_time REAL NOT NULL,
            end_time REAL NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            retry_count INTEGER DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY (session_local_id) REFERENCES recording_sessions(id)
        );

        CREATE INDEX IF NOT EXISTS idx_chunks_status ON pending_chunks(status);
        CREATE INDEX IF NOT EXISTS idx_chunks_session ON pending_chunks(session_local_id);
    ")?;
    Ok(())
}
```

**Step 3: Wire into main.rs**

Add `mod db;` to `main.rs` and register `Database` as Tauri managed state.

**Step 4: Verify it compiles**

```bash
cargo check --manifest-path src-tauri/Cargo.toml
```

**Step 5: Commit**

```bash
git add src-tauri/src/db/
git commit -m "feat: add SQLite database layer with settings, sessions, and chunks tables"
```

---

## Task 4: Audio Engine — Microphone Capture

**Files:**
- Create: `antaranote/src-tauri/src/audio/mod.rs`
- Create: `antaranote/src-tauri/src/audio/mic.rs`
- Create: `antaranote/src-tauri/src/audio/encoder.rs`

**Step 1: Create mic capture module**

`src-tauri/src/audio/mic.rs` — uses `cpal` to:
- `list_devices()` → returns `Vec<AudioDevice { id, name, is_default }>`
- `start_capture(device_id, sender)` → spawns thread, captures PCM 48kHz mono, sends samples via `crossbeam_channel::Sender<Vec<f32>>`
- `stop_capture()` → signals thread to stop

**Step 2: Create Opus encoder**

`src-tauri/src/audio/encoder.rs`:
- `OpusEncoder::new(sample_rate: u32, channels: u8)` → wraps `opus::Encoder`
- `encode_chunk(pcm: &[f32]) -> Vec<u8>` → encode PCM to Opus frames
- `finalize_webm(opus_frames: &[Vec<u8>]) -> Vec<u8>` → wrap in simple WebM/OGG container

**Step 3: Create audio mod.rs**

```rust
pub mod mic;
pub mod encoder;
```

**Step 4: Verify it compiles**

```bash
cargo check --manifest-path src-tauri/Cargo.toml
```

**Step 5: Commit**

```bash
git add src-tauri/src/audio/
git commit -m "feat: add microphone capture with cpal and Opus encoding"
```

---

## Task 5: Audio Engine — System Audio Capture

**Files:**
- Create: `antaranote/src-tauri/src/audio/system_audio.rs`
- Create: `antaranote/src-tauri/src/platform/macos/audio.rs`
- Create: `antaranote/src-tauri/src/platform/windows/audio.rs`
- Create: `antaranote/src-tauri/src/audio/mixer.rs`

**Step 1: Create platform-specific system audio modules**

macOS (`platform/macos/audio.rs`):
- Use `screencapturekit` crate
- `start_system_capture(sender)` → capture system/app audio output
- Requires `NSScreenCaptureUsageDescription` in Info.plist

Windows (`platform/windows/audio.rs`):
- Use `windows` crate WASAPI loopback
- `start_loopback_capture(sender)` → capture all system audio

**Step 2: Create system_audio.rs facade**

```rust
// Delegates to platform-specific implementation
#[cfg(target_os = "macos")]
pub use crate::platform::macos::audio::*;

#[cfg(target_os = "windows")]
pub use crate::platform::windows::audio::*;
```

**Step 3: Create mixer.rs**

- `AudioMixer::new(mic_weight: f32, system_weight: f32)`
- `mix(mic_samples: &[f32], system_samples: &[f32]) -> Vec<f32>` — weighted sum, clamped to [-1.0, 1.0]
- `rms_level(samples: &[f32]) -> f32` — for audio meter UI

**Step 4: Verify it compiles (may need to skip system audio on CI)**

```bash
cargo check --manifest-path src-tauri/Cargo.toml
```

**Step 5: Commit**

```bash
git add src-tauri/src/audio/ src-tauri/src/platform/
git commit -m "feat: add system audio capture (macOS ScreenCaptureKit, Windows WASAPI) and audio mixer"
```

---

## Task 6: Audio Engine Orchestrator

**Files:**
- Create: `antaranote/src-tauri/src/audio/engine.rs`

**Step 1: Create engine orchestrator**

`engine.rs` ties mic + system audio + encoder together:

```rust
pub struct AudioEngine {
    // State
    is_recording: Arc<AtomicBool>,
    chunk_interval_secs: u32,
    chunk_sender: Sender<EncodedChunk>,
}

pub struct EncodedChunk {
    pub data: Vec<u8>,
    pub chunk_number: u32,
    pub start_time: f64,
    pub end_time: f64,
}

impl AudioEngine {
    pub fn new(config: AudioConfig) -> Self { ... }
    pub fn start(&self, mic_device: Option<String>, enable_system: bool) -> Result<()> { ... }
    pub fn pause(&self) { ... }
    pub fn resume(&self) { ... }
    pub fn stop(&self) -> Result<()> { ... }
    pub fn audio_level(&self) -> f32 { ... }
}
```

Flow:
1. Start mic capture thread + system audio thread
2. Mix samples in real-time
3. Every `chunk_interval_secs`, flush buffer → Opus encode → send `EncodedChunk` via channel
4. Streaming client receives chunks on other end of channel

**Step 2: Verify it compiles**

```bash
cargo check --manifest-path src-tauri/Cargo.toml
```

**Step 3: Commit**

```bash
git add src-tauri/src/audio/engine.rs
git commit -m "feat: add AudioEngine orchestrator for mic + system mixing and chunk production"
```

---

## Task 7: Streaming Client

**Files:**
- Create: `antaranote/src-tauri/src/streaming/mod.rs`
- Create: `antaranote/src-tauri/src/streaming/client.rs`
- Create: `antaranote/src-tauri/src/streaming/buffer.rs`

**Step 1: Create HTTP streaming client**

`client.rs`:
```rust
pub struct StreamingClient {
    base_url: String,
    api_key: String,
    http: reqwest::Client,
}

impl StreamingClient {
    pub fn new(base_url: &str, api_key: &str) -> Self { ... }

    pub async fn start_session(&self, meeting_id: i64) -> Result<String> {
        // POST /meetings/{id}/live/start → returns session_id
    }

    pub async fn upload_chunk(&self, meeting_id: i64, session_id: &str, chunk: &EncodedChunk) -> Result<()> {
        // POST /meetings/{id}/live/{sid}/chunk (multipart form)
    }

    pub async fn end_session(&self, meeting_id: i64, session_id: &str) -> Result<()> {
        // POST /meetings/{id}/live/{sid}/end
    }

    pub async fn fetch_meetings(&self) -> Result<Vec<Meeting>> {
        // GET /api/v1/meetings
    }

    pub async fn test_connection(&self) -> Result<bool> {
        // GET /api/v1
    }
}
```

**Step 2: Create offline buffer**

`buffer.rs`:
- `save_pending_chunk(db, session_id, chunk)` → save to SQLite
- `get_pending_chunks(db) -> Vec<PendingChunk>` → query pending chunks
- `mark_uploaded(db, chunk_id)` → update status
- `flush_pending(client, db)` → upload all pending chunks in order

**Step 3: Commit**

```bash
git add src-tauri/src/streaming/
git commit -m "feat: add streaming client with chunked upload and offline SQLite buffer"
```

---

## Task 8: Meeting Detector

**Files:**
- Create: `antaranote/src-tauri/src/detector/mod.rs`
- Create: `antaranote/src-tauri/src/detector/process.rs`
- Create: `antaranote/src-tauri/src/detector/calendar.rs`

**Step 1: Create process scanner**

`process.rs` — uses `sysinfo` crate:
```rust
pub struct MeetingApp {
    pub name: String,      // "Zoom", "Microsoft Teams", etc.
    pub process_name: String,
    pub is_running: bool,
}

pub fn scan_meeting_apps() -> Vec<MeetingApp> {
    // Check: zoom.us, Microsoft Teams, Slack, CiscoWebex
    // Return list of detected apps
}
```

**Step 2: Create calendar sync**

`calendar.rs`:
```rust
pub async fn get_upcoming_meetings(client: &StreamingClient) -> Result<Vec<Meeting>> {
    // Fetch today's meetings from AntaraFlow API
    // Filter to meetings starting within 5 minutes
}
```

**Step 3: Create detector orchestrator**

`mod.rs`:
```rust
pub async fn start_detector(app: AppHandle, interval_secs: u64) {
    // Loop every interval_secs:
    //   1. scan_meeting_apps()
    //   2. get_upcoming_meetings() (every 60s, not every 5s)
    //   3. If new meeting detected → emit "meeting-detected" event to frontend
    //   4. Cooldown: skip if same app dismissed within 10 min
}
```

**Step 4: Commit**

```bash
git add src-tauri/src/detector/
git commit -m "feat: add meeting detector with process scanning and calendar sync"
```

---

## Task 9: Tauri Commands (IPC Bridge)

**Files:**
- Create: `antaranote/src-tauri/src/commands/mod.rs`
- Create: `antaranote/src-tauri/src/commands/recording.rs`
- Create: `antaranote/src-tauri/src/commands/devices.rs`
- Create: `antaranote/src-tauri/src/commands/meetings.rs`
- Create: `antaranote/src-tauri/src/commands/settings.rs`

**Step 1: Create recording commands**

`commands/recording.rs`:
```rust
#[tauri::command]
pub async fn start_recording(app: AppHandle, meeting_id: i64, mic_device: Option<String>, enable_system_audio: bool) -> Result<String, String> { ... }

#[tauri::command]
pub async fn stop_recording(app: AppHandle) -> Result<(), String> { ... }

#[tauri::command]
pub async fn pause_recording(app: AppHandle) -> Result<(), String> { ... }

#[tauri::command]
pub async fn resume_recording(app: AppHandle) -> Result<(), String> { ... }

#[tauri::command]
pub fn get_audio_level(app: AppHandle) -> f32 { ... }

#[tauri::command]
pub fn get_recording_state(app: AppHandle) -> RecordingState { ... }
```

**Step 2: Create device commands**

`commands/devices.rs`:
```rust
#[tauri::command]
pub fn list_audio_devices() -> Vec<AudioDevice> { ... }
```

**Step 3: Create meeting commands**

`commands/meetings.rs`:
```rust
#[tauri::command]
pub async fn fetch_meetings(app: AppHandle) -> Result<Vec<Meeting>, String> { ... }

#[tauri::command]
pub async fn test_connection(app: AppHandle) -> Result<bool, String> { ... }
```

**Step 4: Create settings commands**

`commands/settings.rs`:
```rust
#[tauri::command]
pub fn get_setting(app: AppHandle, key: String) -> Option<String> { ... }

#[tauri::command]
pub fn set_setting(app: AppHandle, key: String, value: String) -> Result<(), String> { ... }

#[tauri::command]
pub fn get_api_key(app: AppHandle) -> Option<String> { ... }  // from OS keychain

#[tauri::command]
pub fn set_api_key(app: AppHandle, key: String) -> Result<(), String> { ... }
```

**Step 5: Wire all commands into main.rs**

```rust
fn main() {
    tauri::Builder::default()
        .plugin(tauri_plugin_notification::init())
        .plugin(tauri_plugin_global_shortcut::init())
        .manage(Database::new(app_dir).unwrap())
        .invoke_handler(tauri::generate_handler![
            commands::recording::start_recording,
            commands::recording::stop_recording,
            commands::recording::pause_recording,
            commands::recording::resume_recording,
            commands::recording::get_audio_level,
            commands::recording::get_recording_state,
            commands::devices::list_audio_devices,
            commands::meetings::fetch_meetings,
            commands::meetings::test_connection,
            commands::settings::get_setting,
            commands::settings::set_setting,
            commands::settings::get_api_key,
            commands::settings::set_api_key,
        ])
        .setup(|app| {
            // Setup system tray
            // Start meeting detector
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error running antaraNote");
}
```

**Step 6: Commit**

```bash
git add src-tauri/src/commands/ src-tauri/src/main.rs
git commit -m "feat: add Tauri IPC commands for recording, devices, meetings, and settings"
```

---

## Task 10: System Tray Setup

**Files:**
- Create: `antaranote/src-tauri/src/tray.rs`
- Modify: `antaranote/src-tauri/src/main.rs`

**Step 1: Create tray module**

`tray.rs`:
```rust
use tauri::{
    tray::{TrayIconBuilder, TrayIconEvent},
    AppHandle, Manager,
};

pub fn setup_tray(app: &AppHandle) -> Result<(), Box<dyn std::error::Error>> {
    let tray = TrayIconBuilder::new()
        .icon(app.default_window_icon().unwrap().clone())
        .tooltip("antaraNote")
        .on_tray_icon_event(|tray, event| {
            match event {
                TrayIconEvent::Click { .. } => {
                    // Toggle popup window visibility
                    if let Some(window) = tray.app_handle().get_webview_window("main") {
                        if window.is_visible().unwrap_or(false) {
                            window.hide().ok();
                        } else {
                            window.show().ok();
                            window.set_focus().ok();
                        }
                    }
                }
                _ => {}
            }
        })
        .build(app)?;

    Ok(())
}
```

**Step 2: Configure window as popup (not regular window)**

Edit `tauri.conf.json`:
- Set window `visible: false` (starts hidden)
- Set `decorations: false` (no title bar)
- Set `width: 380, height: 520`
- Set `alwaysOnTop: true`
- Set `skipTaskbar: true`

**Step 3: Commit**

```bash
git add src-tauri/src/tray.rs src-tauri/tauri.conf.json
git commit -m "feat: add system tray with popup window toggle"
```

---

## Task 11: React Frontend — Idle State UI

**Files:**
- Create: `antaranote/src/App.tsx`
- Create: `antaranote/src/components/RecordingPanel.tsx`
- Create: `antaranote/src/components/DeviceSelector.tsx`
- Create: `antaranote/src/components/MeetingPicker.tsx`
- Create: `antaranote/src/components/ConnectionStatus.tsx`
- Create: `antaranote/src/lib/tauri.ts`

**Step 1: Create Tauri IPC wrappers**

`src/lib/tauri.ts`:
```typescript
import { invoke } from '@tauri-apps/api/core';

export const listDevices = () => invoke<AudioDevice[]>('list_audio_devices');
export const fetchMeetings = () => invoke<Meeting[]>('fetch_meetings');
export const startRecording = (meetingId: number, micDevice?: string, enableSystem?: boolean) =>
    invoke<string>('start_recording', { meetingId, micDevice, enableSystemAudio: enableSystem ?? true });
export const stopRecording = () => invoke('stop_recording');
export const pauseRecording = () => invoke('pause_recording');
export const getAudioLevel = () => invoke<number>('get_audio_level');
export const getRecordingState = () => invoke<RecordingState>('get_recording_state');
export const testConnection = () => invoke<boolean>('test_connection');
export const getSetting = (key: string) => invoke<string | null>('get_setting', { key });
export const setSetting = (key: string, value: string) => invoke('set_setting', { key, value });
export const getApiKey = () => invoke<string | null>('get_api_key');
export const setApiKey = (key: string) => invoke('set_api_key', { key });
```

**Step 2: Build idle state UI components**

RecordingPanel with:
- Device selectors (mic dropdown, system audio toggle)
- Meeting picker dropdown
- Big "START RECORD" button
- Connection status bar at bottom

**Step 3: Style with Tailwind (violet/slate theme matching AntaraFlow)**

**Step 4: Verify with `npm run tauri dev`**

**Step 5: Commit**

```bash
git add src/
git commit -m "feat: add idle state UI with device selector, meeting picker, and recording panel"
```

---

## Task 12: React Frontend — Recording State UI

**Files:**
- Create: `antaranote/src/components/RecordingView.tsx`
- Create: `antaranote/src/components/AudioMeter.tsx`
- Create: `antaranote/src/components/LiveTranscript.tsx`
- Create: `antaranote/src/components/VoiceNoteButton.tsx`
- Create: `antaranote/src/hooks/useRecording.ts`

**Step 1: Build recording view**

RecordingView with:
- Red recording indicator + timer (HH:MM:SS)
- AudioMeter component (polls `get_audio_level` at 60fps via requestAnimationFrame)
- LiveTranscript component (listens to Tauri events for transcript chunks)
- Pause / Stop / Voice Note buttons

**Step 2: Create useRecording hook**

```typescript
// Manages recording state, timer, transitions
export function useRecording() {
    const [state, setState] = useState<'idle' | 'recording' | 'paused'>('idle');
    const [elapsed, setElapsed] = useState(0);
    // ...
}
```

**Step 3: Verify with `npm run tauri dev`**

**Step 4: Commit**

```bash
git add src/
git commit -m "feat: add recording state UI with timer, audio meter, and live transcript"
```

---

## Task 13: React Frontend — Settings Page

**Files:**
- Create: `antaranote/src/components/Settings.tsx`
- Create: `antaranote/src/components/RecordingHistory.tsx`

**Step 1: Build settings page**

Settings with:
- AntaraFlow URL input
- API key input (masked) + "Test Connection" button
- Default mic device dropdown
- Chunk interval selector (15s / 30s / 60s)
- Auto-record toggle
- Auto-start on login toggle
- Global hotkey display

**Step 2: Build recording history**

RecordingHistory:
- List of past sessions from SQLite
- Status badges (completed, uploading, failed)
- Duration display

**Step 3: Commit**

```bash
git add src/
git commit -m "feat: add settings page and recording history"
```

---

## Task 14: Global Hotkey + Auto-start

**Files:**
- Modify: `antaranote/src-tauri/src/main.rs`

**Step 1: Register global hotkey**

Using `tauri-plugin-global-shortcut`:
- macOS: `Cmd+Shift+R`
- Windows: `Ctrl+Shift+R`
- Toggle: start recording if idle, stop if recording

**Step 2: Configure auto-start on login**

Using `tauri-plugin-autostart`:
- Toggled from settings UI
- Stored in SQLite settings table

**Step 3: Commit**

```bash
git add src-tauri/src/main.rs
git commit -m "feat: add global hotkey (Cmd/Ctrl+Shift+R) and auto-start on login"
```

---

## Task 15: Integration Testing — End to End

**Step 1: Test connection to AntaraFlow**

- Configure API key in settings
- Click "Test Connection" → should show green checkmark
- Fetch meetings → should show meeting list

**Step 2: Test recording flow**

- Select meeting from dropdown
- Click "Start Record"
- Verify audio level meter moves
- Wait 30s → verify chunk uploaded (check AntaraFlow live session)
- Click "Stop" → verify session ends

**Step 3: Test meeting detector**

- Open Zoom/Teams
- Verify notification appears
- Accept → starts recording

**Step 4: Test offline recovery**

- Start recording
- Disconnect network
- Continue recording 2 minutes
- Reconnect → verify pending chunks flush

**Step 5: Build release**

```bash
npm run tauri build
```

Expected: `.dmg` (macOS) and `.msi` (Windows) in `src-tauri/target/release/bundle/`

**Step 6: Commit**

```bash
git add .
git commit -m "chore: finalize antaraNote v1.0.0 release build"
```

---

## Build Sequence Summary

```
Task  1: Scaffold Tauri v2 project
Task  2: Add Rust dependencies
Task  3: SQLite database layer
Task  4: Audio — mic capture + Opus encoding
Task  5: Audio — system audio (macOS + Windows)
Task  6: Audio engine orchestrator
Task  7: Streaming client + offline buffer
Task  8: Meeting detector
Task  9: Tauri IPC commands
Task 10: System tray setup
Task 11: Frontend — idle state UI
Task 12: Frontend — recording state UI
Task 13: Frontend — settings + history
Task 14: Global hotkey + auto-start
Task 15: Integration testing + release build
```

Estimated: ~3-5 days for experienced Rust/React developer.
