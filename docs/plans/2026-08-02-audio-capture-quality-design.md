# Audio Capture Quality Design

**Date:** 2026-08-02
**Problem:** Distant speakers, quiet voices, and unusable recordings in physical and hybrid meetings
**Approach:** Fix capture first, then server-side restoration, then multi-device — staged, each phase independently shippable

## Context

Two setups drive the complaints: a laptop sitting on a meeting-room table, and hybrid meetings mixing in-room voices with remote participants through the laptop speaker.

Constraints agreed with the user:

- **Software only** — no hardware purchase may be required
- **Participant phones are fair game** — people already own them, so using them as satellite mics stays within "use what's available"
- **Multi-device merge = loudest-stream selection** — keeps transcription cost at 1x and yields speaker identity for free
- **Post-meeting first**, live mode phased later
- **ffmpeg only** for enhancement — no paid enhancement API, no self-hosted ML model

## Findings that shaped the design

### The current constraints work against room recording

`resources/js/audio-recorder.js:191` requests `echoCancellation`, `noiseSuppression` and `autoGainControl` all `true`. That triple is tuned for one person close to a mic on a video call. In a room:

- `noiseSuppression` classifies distant and quiet speech as noise and gates it out — the primary cause of the reported problem
- `echoCancellation` suppresses room reflections, which is precisely where a distant speaker's energy lives
- `autoGainControl` pumps between loud and quiet talkers, making both inconsistent

There is a documented case where these three defaults produced entirely silent audio, resolved by disabling all of them.

### The browser's own recording indicator has been stripped of meaning

`init()` (`audio-recorder.js:86`) calls `checkExistingPermission()`, which calls `setupStream()` on page load whenever permission is already granted (`audio-recorder.js:155`). The browser's red mic indicator therefore lights up the moment the page opens, long before the user presses record — and nothing changes at browser level when recording actually starts.

### Motion currently means nothing

`drawWaveform()` animates in `ready`, `paused` and `recording` alike (`audio-recorder.js:274`), mixing synthetic sine waves with the real level. The most visually prominent element on screen moves whether or not anything is being captured.

### Physics beats post-processing

In rooms of six or more people, the participant farthest from a single microphone is commonly garbled or missed entirely. No amount of post-processing recovers what the microphone never captured. This is why Phase 3 stays in the plan rather than being treated as optional polish.

### Over-processing clean audio makes it worse

Enhancement must be conditional on measurement, not applied uniformly. Meetings that already sound fine get worse under an always-on chain.

## Phase 1 — Capture layer (client)

### 1a. Raw constraints

`setupStream()` (`audio-recorder.js:191`) becomes:

```js
audio: {
    echoCancellation: false,
    noiseSuppression: false,
    autoGainControl: false,
    voiceIsolation: false,
    channelCount: 1,
    deviceId: saved ? { exact: saved } : undefined,
}
```

Disabling AEC has a useful side effect: Chrome stops forcing the narrow processing path and hands over full-band device audio.

### 1b. Our own gain chain before MediaRecorder

MediaRecorder encodes to Opus at low bitrate. When a distant speaker sits at -50 dBFS, the encoder allocates almost no bits to them, and server-side `loudnorm` later amplifies encoder noise rather than speech. That information is destroyed at encode time, so gain must be applied before encoding.

The existing `AudioContext` (`audio-recorder.js:202`) extends into a real chain:

```
MediaStreamSource
  → BiquadFilter (highpass 80Hz)
  → DynamicsCompressor
  → GainNode
  → AnalyserNode (existing)
  → MediaStreamDestination → MediaRecorder
```

If `AudioContext` construction fails, the chain falls back to the raw stream. A recording must never be silent because of this chain.

### 1c. Microphone picker

`enumerateDevices()` filtered to `audioinput`. Device labels only populate after permission is granted, so enumeration happens after `getUserMedia`, not before. Selection persists in `localStorage`.

### 1d. Real level meter and in-flight warnings

`drawWaveform()` stops mixing synthetic sine waves and renders measured level only. On top of that:

- **Pre-flight mic check** — five seconds, "speak normally", verdict delivered before the meeting starts
- **Live warning** — speech-band RMS staying below roughly -40 dBFS for 15 seconds triggers "voice too quiet, move the laptop closer"
- **Clipping warning** — peak above -1 dBFS

This converts the failure mode from "discovered after the meeting" to "fixable during it".

### 1e. Unmistakable recording state

Root cause first: the stream opens at mic-check time rather than page load, and tracks stop when returning to idle. The browser's mic indicator regains its meaning. Reopening the mic costs roughly 200-500 ms, fully hidden by the existing three-second countdown (`audio-recorder.js:326`).

Second, with the real level meter from 1d, bars move only when sound is arriving *and* recording is active. Idle and paused are flat and still.

Added signals:

| Signal | Rationale |
|---|---|
| Worded status pill — `● RECORDING 12:34` | Colour alone fails for colour-blind users and reads as too subtle |
| Pill sticks when scrolled | Users scroll to agenda and notes; mind the mobile bottom nav (commit `69da68d`) |
| Dynamic tab title | Works after the user has switched tabs entirely |
| Red-dotted favicon | Survives tab-title truncation |
| Wake lock | Screen stays on, indicator stays visible, recording is safer on mobile |

Deliberately excluded: Media Session API (patchy support for recording rather than playback) and more elaborate audio cues.

### 1f. Micro-interactions

Governing principle: **every animation must carry information.** Phase 1e exists because decorative motion destroyed trust in the status signal; adding decorative motion now would undo it.

**Scrolling tape waveform** replaces 40 bars oscillating in place. New bars enter from the right, history scrolls left. Oscillation-in-place looks identical at second 5 and second 500; a scrolling tape produces a unique, unfakeable history — visual proof that something real is being captured, and it exposes quiet stretches, reinforcing the 1d warning. Keep a 60-second visible ring buffer and downsample older data so a two-hour mobile recording does not grow memory.

Existing conventions to match: spring easing `cubic-bezier(.34,1.56,.64,1)` at ~.35s, brand palette (Nusantara Teal `#0D7377`, Amber Gold `#D97706`, Crimson `#DC2626`).

| Moment | Motion |
|---|---|
| Countdown 3→2→1 | Digits pop with spring easing, colour walks teal → amber → crimson |
| Start | Circle morphs to rounded square; tape wakes, bars sweeping up left-to-right over ~400 ms |
| Stop | Mirror image: bars collapse to the centre line, sweeping right-to-left |
| Processing | Bars become an indeterminate shimmer travelling left-to-right |
| Complete | Checkmark pop reusing `_cm-icon`, plus `_cm-strip` |

**Synced pulse** — the `●` on the status pill beats at exactly 1 Hz, driven by the timer tick rather than an independent CSS cycle, so the beat and the digit change land together. A pulse drifting out of phase with the timer is exactly the signal that teaches users animation is meaningless.

**Physical level meter** — ~30 ms attack, ~200 ms release, like a real VU meter. Peak-hold line hangs 800 ms before falling. Colour gradient teal → amber → crimson across height, so the 1d clipping warning surfaces inside the meter itself.

**Non-nagging warning** — the "too quiet" pill springs down in amber with one `_cm-shake`, **once**, then shrinks to a persistent small amber dot. A repeating warning gets ignored, and worse, interrupts whoever is running the meeting.

**`prefers-reduced-motion`** — everything degrades to instant state changes. The level meter keeps updating because it is data, not decoration; it only loses spring and decay easing. Note that `confirm-modal.blade.php` currently has no reduced-motion handling at all; out of scope here, but worth aligning once this sets the pattern.

**Accepted tension:** an idle breathing record button technically violates "motion means recording". Kept, at very low amplitude (2.5 s cycle, 3% scale) and confined to the button. The button is a call to action; the waveform is the status display, and it stays flat and dead while idle.

## Phase 2 — Server-side ffmpeg chain

### One encode, not two

Compression currently runs only above 25 MB (`ProcessTranscriptionJob.php:75`), so most recordings never touch ffmpeg. Rather than adding a separate enhancement step — two encodes and generational loss — the filter chain is added inside the existing `encodeToOpus()` (`ProcessTranscriptionJob.php:222`) and that path always runs. The compression retry loop already re-encodes from the original source each iteration, so repetition costs no quality. Small files use `MAX_BITRATE`.

### The chain

```
highpass=f=80          → remove HVAC and room rumble
acompressor            → close the gap between loud and quiet speakers
loudnorm (two-pass)    → consistent final level, I=-16 LUFS
```

Order is deliberate. Highpass runs first so low-frequency rumble does not consume compressor headroom or skew the loudness measurement. Loudnorm runs last because it must see the final signal to hit its target.

### Conditional processing, at no extra cost

Loudnorm's first pass already measures `input_i`, `input_tp` and `input_lra`. That measurement is free, so the chain is chosen from real data:

| Measurement | Meaning | Action |
|---|---|---|
| `input_i` < -30 LUFS | Genuinely too quiet | Full chain, aggressive compressor |
| `input_lra` > 15 | Wide gap between loud and quiet speakers | Compressor on — this is the distant-speaker case |
| `input_i` > -23 LUFS, moderate LRA | Already healthy | Skip compressor, gentle loudnorm only |

### Two things deliberately left out

**Denoise (`afftdn`) — not now.** Whisper tolerates steady noise reasonably well but not gating artefacts; `afftdn` produces musical noise, a known hallucination trigger. Ship without it, measure, add behind a flag if the data demands it.

**Silence trimming (VAD) — not possible.** `transcription_segments` stores `start_time`/`end_time` used by the UI to seek within audio. Removing silence desynchronises every subsequent timestamp. The benefit does not justify breaking transcript navigation.

### Failure must not kill transcription

`AudioStorageService.php:115` already establishes the fallback pattern for a missing ffmpeg. Enhancement follows it: on missing binary, timeout, or rejected input, log a warning, pass the original file through untouched, and continue. Someone's meeting recording must never fail because of an audio filter.

New class: `app/Domain/Transcription/Services/AudioEnhancer.php`, alongside the existing `AudioStorageService` and `SpeakerDiarizationService`.

### Scope: upload path only

Live 30-second chunks stay untouched this phase. Normalising each chunk independently gives every 30 seconds a different gain, producing an inconsistency worse than the original problem. Live handling belongs to the later live phase, where it needs a gain profile shared across chunks.

### Cost

Every recording now gets a measurement pass plus an encode pass — roughly 30-60 seconds of CPU for an hour-long meeting on the DirectAdmin box. It is queued, so users do not wait, but it is new server load for files that previously bypassed ffmpeg entirely.

## Phase 3 — Participant phones as satellite mics

### The hidden win: real names instead of "Speaker 1"

Today, when the model does not support diarization, `assignSpeakers()` guesses a new speaker on every 1.5-second gap (`ProcessTranscriptionJob.php:137`). Someone pausing to think becomes "Speaker 2".

With satellite mics, loudest-stream selection already reveals *whose* phone won, and that phone was registered to a named person at QR check-in. The output is therefore better audio **plus an ownership map** that turns `Speaker 1` into a real name — diarization derived from physics and registration rather than inference.

### Join flow

Scan QR → register (existing) → new screen: "Make this phone an extra microphone?" → the phone uploads 30-second chunks. Reuses the existing public token route pattern (`routes/web.php:79`); no login, same as registration.

### Clock sync — and the real hard part

The obvious problem, phones not sharing a clock, is solved by NTP-style offset estimation, accurate to roughly 100 ms, which is sufficient for one-second selection windows.

The *real* problem is sample-rate drift: phone A records at an effective 47,999 Hz while phone B records at 48,001 Hz. Across a two-hour meeting that accumulates into seconds of drift, and a global timeline would break silently near the end — the worst class of bug.

The design avoids it entirely: **align per chunk, not on a global timeline.** The laptop recording is the reference track. Each phone's chunk N is aligned against the laptop's chunk N window by cross-correlation — every device hears the same room, so they share acoustic content to correlate against. Within a 30-second window, drift is under a millisecond and irrelevant. No global timeline means no accumulated drift.

### Selection

Per one-second window, compute a SNR-like score per device (speech-band RMS against noise floor) and take the highest. Two guards:

- **Hysteresis** — require a 3 dB margin *and* a one-second minimum dwell before switching, so it cannot flip-flop mid-word
- **30 ms crossfade** at switch points, so no click confuses Whisper

Output is a single stitched mono track entering the Phase 2 pipeline normally.

### Failures must fall down, never sideways

Satellites are **additive, never required.** The laptop recording is the floor; if every phone fails, behaviour is exactly what it is today.

This is not excess caution. Mobile browser reality: **iOS Safari suspends MediaRecorder when the screen locks or the tab backgrounds.** The wake lock from 1e helps, but this will still fail for some users. The laptop track must stay authoritative — treated as something we expect to need often, not a rare fallback.

### Privacy — designed, not footnoted

We are asking people to record a room using their personal phones, which differs fundamentally from the host's laptop recording.

- **Explicit consent** on the join screen, stating that audio goes to the organiser's organization, not to their phone
- **Always-visible indicator** on the phone, plus a large, permanently available "stop being a mic" button
- **Automatic stop when the host ends the session** — mandatory. A forgotten phone still recording after the meeting is a serious failure, not a minor annoyance
- **Delete satellite audio after a successful stitch** — retain the stitched master only; keep satellites only when stitching failed

### Schema

Two new tables, following the shape of `live_transcript_chunks`:

- `meeting_mic_devices` — meeting/session, nullable `attendee_id`, device label, `joined_at`, `last_seen_at`, status
- `mic_device_chunks` — device id, chunk number, path, `offset_ms`, RMS score, status

`StitchMicChunksJob` runs at finalize, producing the master track plus the ownership map.

### Structure

New bounded context `app/Domain/MultiMic/`. The work spans `Attendee` (identity), `LiveMeeting` (session) and `Transcription` (output), and has its own domain language — devices, alignment, selection — that none of the three should own.

## Error handling, testing, and verification

### Testing audio without testing audio

Audio DSP is notoriously hard to test. The approach is to separate risky logic from ffmpeg — nearly every important decision is a pure function over numbers:

| Subject | Test |
|---|---|
| Conditional chain decision | Feed loudnorm measurement JSON, assert which chain is selected. No audio needed |
| Device selection algorithm | Feed synthetic RMS arrays, assert the 3 dB margin and one-second dwell hold |
| Cross-correlation alignment | Synthetic signal shifted by a known offset, assert the offset is recovered |
| Missing-ffmpeg fallback | `Process::fake()` returns failure; assert the original file passes through and transcription still succeeds |

Client tests join the existing `tests/Feature/Domain/Transcription/BrowserRecordingIntegrationTest.php`. Pest 4 can drive a real browser, but `getUserMedia` needs Chrome's fake-device flags — so browser coverage targets **status signals** (pill appears, tab title changes, meter is dead while idle) rather than audio quality.

### Project-specific migration trap

For `meeting_mic_devices` and `mic_device_chunks`, a bare `$table->timestamp('joined_at')` silently acquires `ON UPDATE CURRENT_TIMESTAMP` on production MySQL, invisible to SQLite in tests.

For ordinary tables that corrupts data. For **mic alignment tables it is fatal** — a timestamp shifted by 8 hours destroys chunk alignment outright, and tests would never catch it. Every time column in these migrations must declare its default explicitly.

### How we will know it worked

The user chose free ffmpeg first on the implied condition of "upgrade if the data demands it" — so that data has to exist. Two numbers are already available at no cost:

1. **`confidence_score`**, already stored per `audio_transcriptions` (`ProcessTranscriptionJob.php:93`). Mean before versus after is the real indicator
2. **`input_i` and `input_lra`** from the loudnorm measurement pass — store both on the transcription record

The second diagnoses rather than merely scores. If complaints persist **and** `input_i` is still low after Phase 1, the microphone genuinely is not capturing — that argues for Phase 3, not for paid enhancement. If `input_i` is healthy but confidence stays low, the problem is not level, and only then does a paid enhancement model become worth considering.

Without this, "do we need the paid API?" is guesswork.

### Shipping order

Phases 1 and 2 each stand alone. Ship Phase 1, let it run long enough to gather confidence data, then ship Phase 2. Shipping both together means never learning which one mattered.

## Honest limits

With software only and a laptop on the table, the ceiling is real. Phases 1 and 2 recover a large share of quiet-voice cases, but the large-room case genuinely needs Phase 3 — which is why it is in the plan rather than cut.
