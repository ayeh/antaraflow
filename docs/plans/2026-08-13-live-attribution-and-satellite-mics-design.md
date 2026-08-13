# Live Speaker Attribution and Satellite Microphones Design

**Date:** 2026-08-13
**Problem:** Live-recorded meetings come back as an unattributed wall of text, and the person at the far end of the table is still the one who goes missing
**Approach:** Two independent changes — segments and named speakers on the live path (C1), then a second device as a satellite microphone (B1) — sequenced so the cheap one that unlocks an existing feature ships first

## Context

This continues `2026-08-02-audio-capture-quality-design.md`. Phases 1 and 2 of that document have shipped:

- **Phase 1** — raw capture constraints and a gain chain in the browser recorder, deployed via PR #49/#50.
- **Phase 2** — `AudioConditioner` (`app/Domain/Transcription/Services/AudioConditioner.php`) applies `highpass=f=80,loudnorm=I=-16:TP=-1.5:LRA=11` at 16 kHz mono. PR #60 wired it into `LiveTranscriptionJob`, where previously only the upload path had it. **Treat Phase 2 as done; it is not outstanding work.**
- The mobile recorder measures the room and ships `peak_dbfs`, `speech_dbfs` and `noise_dbfs` with every chunk (`mobile/lib/features/recorder/room_level.dart`), warns the user during a sitting, and checks placement at the start.

**Phase 3 of that document — participant phones as satellite mics — is superseded by B1 below.** Its central architectural choice is reversed, for reasons that did not exist when it was written.

Constraints carried forward unchanged: software only, no hardware purchase, ffmpeg only, no paid enhancement API.

## Findings that shaped the design

### The two transcription paths look alike and are not

> **Corrected 2026-08-13, during implementation.** An earlier revision of this
> section claimed the live path creates no segments at all. That was wrong —
> `mergeChunksIntoTranscription()` has a `TranscriptionSegment` loop after the
> transcription insert. The table and the two sections below are the verified
> position. C1 remains worth doing; its shape changed from *create segments* to
> *make the existing segments fine-grained, and actually run diarization*.

| | Upload path | Live path |
|---|---|---|
| Entry | `ProcessTranscriptionJob` | `LiveTranscriptionJob` |
| Unit | whole file, split at 10 min | one 15 s chunk, independently |
| `transcription_segments` | one per utterance (`ProcessTranscriptionJob.php:134`) | **one per 15 s chunk** (`LiveMeetingService.php:254`) |
| Speaker labels | `assignSpeakers()` gap heuristic (`ProcessTranscriptionJob.php:253`) | copied from `live_transcript_chunks.speaker`, **always null** |
| Diarization | never dispatched automatically | never dispatched automatically |

So a live meeting does have segment structure — it is just far too coarse to attribute. One segment covers a whole fifteen-second chunk regardless of who spoke in it or how many sentences it contains, and its `speaker` comes from `$result->segments[0]->speaker` (`LiveTranscriptionJob.php:103`), which Whisper leaves null.

### The diarization feature is built and never runs

`SpeakerDiarizationService` sends segments plus the meeting's `is_present` attendees to an LLM and maps segments to real names. It is invoked from exactly one place — `SpeakerDiarizationController`, a manual endpoint. **Nothing dispatches it automatically, on either path.**

That is the cheapest available win, and it is why C1 is sequenced first. Feeding it per-utterance segments instead of fifteen-second blocks is what makes its output worth having.

### Extraction reads `full_text`, not segments

`ExtractionService` collects `$transcription->full_text` (`ExtractionService.php:189`). Adding segments therefore cannot disturb minutes extraction or action items — segments are purely additive, for display and for diarization to work on.

### Chunk identity has no room for a second device

`findExistingChunk()` (`LiveMeetingService.php:100`) looks up `(session, chunk_number)`. A second device uploading its chunk 12 would be answered `CHUNK_DUPLICATE` and would drop that audio from its queue. `getResumeState()` (`LiveMeetingService.php:173`) computes `next_chunk_number` from the highest chunk in the whole session, so two devices would fight over the same counter.

This is the one genuine schema blocker for B1, and it fails silently in the worst way: the satellite would report a clean upload for audio that was thrown away.

### Chunk deduplication has no constraint behind it

Worth noting while in this code: `live_transcript_chunks` has a plain index on `(live_meeting_session_id, chunk_number)` and **no unique constraint** (`2026_03_08_085523_create_live_transcript_chunks_table.php:28`). Deduplication is entirely a check-then-insert in `findExistingChunk()` followed by `processChunk()`, which two concurrent retries of the same chunk can both pass. The window is small and the outbox uploads serially, so this has probably never bitten — but B1 touches exactly this key, and adding the device dimension is the moment to put a real constraint behind it rather than a convention.

### Per-chunk confidence and level readings change the multi-device trade

The 2026-08-02 design specified loudest-stream selection: cross-correlate the satellite against a reference track, pick the best device per one-second window, crossfade at switch points, transcribe the stitched result once. That was the right call for browser satellites merging before a single transcription.

Two things now exist that did not:

1. Live chunks are **already transcribed independently** at 15 s, each returning a `confidence`.
2. Every chunk now carries **a measured level** from the device that recorded it.

Together these make a far simpler architecture available, described below.

## B1 — A second device as a satellite microphone

### Transcribe both, do not mix

This reverses the earlier design. Both approaches are viable; the trade has moved.

| | Mix then transcribe (2026-08-02) | Transcribe both (B1) |
|---|---|---|
| Alignment needed | sample-accurate, by cross-correlation | chunk number only |
| New DSP | selection, hysteresis, 30 ms crossfade | none |
| Transcription cost | 1× | 2×, and conditional — see below |
| Selection granularity | 1 s | 15 s |
| New silent-failure class | yes — misalignment corrupts the transcript with nothing reporting it | no — the worst case is picking the weaker of two real transcripts |

The decisive argument is the failure mode, not the cost. A misaligned mix produces a plausible, wrong transcript and nothing anywhere says so; that is the same class of bug as the Android background microphone returning zeroes, which this project has already been bitten by. Per-chunk selection cannot fail that way — every candidate is a real transcript of real audio, and the worst outcome is choosing the poorer one.

What is genuinely given up: within a single 15 s chunk, one speaker may be near the phone and another near the laptop, and neither device's transcript is best for the whole window. Mixing wins there. We do not yet know how often that matters, and B1 produces exactly the data needed to find out — see *How we will know it worked*.

**If per-chunk selection proves too coarse, mixing remains open and we will build it knowing it is worth it.** Shipping the hard version first means never learning whether the easy one was enough.

### Joining a session is structurally a rejoin

The machinery already exists. `POST /meetings/{id}/live/start` answers a second start with 409 `SESSION_ALREADY_ACTIVE` plus `resume.next_chunk_number`, and the mobile recorder already handles resuming from a chunk number and an elapsed offset (`AudioChunker.prepare()`'s `fromChunk` and `alreadyRecorded`).

A satellite joining mid-meeting needs the same two values plus one new capability: its **first cut must be short**, so its chunk boundaries land on the primary's. A device joining 37 s into a sitting cuts 8 s first, then settles into 15 s. `AudioChunker` currently cuts only at `_bytesPerChunk` (`audio_chunker.dart:189`); this becomes a one-shot smaller first target.

Sample-rate drift over an hour is around half a second. Within a 15 s window that changes no selection, and because B1 never mixes samples, drift cannot accumulate into anything audible. This is the earlier design's "align per chunk, never on a global timeline" argument, and transcribing both makes it weaker still.

### Schema

On `live_transcript_chunks`:

- `device_id` — the stable per-installation identifier the app already keeps in `secure_store` and sends when authenticating (`mobile/lib/data/local/secure_store.dart:30`); nothing new has to be generated
- `role` — `primary` or `satellite`; the device that opened the session is always primary
- a **unique** index on `(live_meeting_session_id, device_id, chunk_number)`, which is also the constraint that has never existed

`findExistingChunk()` and `getResumeState()` both take a device. Existing rows backfill to the session's `started_by` device with role `primary`; the web recorder, which sends no device, is treated as primary with a null device and keeps working untouched — `LiveMeetingController::chunk()` needs no change.

### Selection

At `endSession()`, for each chunk number, choose the winning text:

1. Prefer the chunk with the higher `confidence`.
2. Break ties, and override a marginal confidence gap, using `speech_dbfs` — a chunk whose speech landed 12 dB louder is the better source even if the model reports a similar score.
3. If only one device produced that chunk number, it wins by default.

Selection happens once, in `mergeChunksIntoTranscription()`, over rows that already exist. No audio is re-opened.

### Cost, and the switch that keeps it small

A satellite doubles transcription for the chunks it covers. At Whisper rates that is roughly $0.36 per recorded hour.

It should not be spent uniformly. **Transcribe a satellite chunk only when the primary's own reading says it needs help** — `speech_dbfs` below the faint threshold, or the primary chunk failed. On a well-placed primary that is a small minority of chunks. Satellite audio is always uploaded and stored; only the transcription is conditional, so the decision can be revisited later against audio we still hold.

`OrgBudgetService` and the `live_transcription` circuit breaker already gate this spend and need no changes.

### Failures must fall down, never sideways

The primary recording is the floor. If the satellite never joins, drops out, is killed by the OS, or uploads nothing, the result is exactly what the product does today. A satellite is additive and must never be on the critical path — no session may block on one, and `endSession()` must not wait for one.

### Scope

Authenticated members of the organisation only. Guest phones joining by QR token, as the earlier design proposed, bring a consent and privacy story that deserves its own document, and the realistic first case is two staff devices in one room. Deferred deliberately, not forgotten.

## C1 — Segments and names on the live path

### What is missing is granularity, and a dispatch

`LiveTranscriptionJob` receives `$result->segments` and discards them, keeping only `segments[0]->speaker`. C1 keeps them all, so a segment becomes an utterance rather than a fifteen-second block — and then runs the diarization that has never been wired up.

The coarse per-chunk segment the merge writes today is the fallback, not the target: it stays for chunks that carry no segments of their own, which is every recording made before this change and everything the web recorder produces.

Each live chunk is transcribed with its own clock starting at zero, so segment times must be shifted by the chunk's `start_time` before they mean anything on the meeting's timeline — the same offset arithmetic `ProcessTranscriptionJob::transcribeChunked()` already does for its 10-minute pieces.

Segments are written when the session ends, alongside the merge, rather than per chunk: a chunk can be retried, and re-running a chunk must not leave two copies of its segments behind.

### Labelling is one pass at the end, never per chunk

`assignSpeakers()` must **not** be reused here. It numbers speakers by counting 1.5 s gaps, and live chunks are transcribed independently — "Speaker 2" in chunk 5 and "Speaker 2" in chunk 6 would be unrelated people. Any per-chunk heuristic produces confident nonsense.

Instead, after the merge, run the existing `SpeakerDiarizationService::diarize()` once over the whole transcription. It already receives the attendee list and is explicitly instructed to fall back to consistent `Speaker N` numbering when it cannot match a name.

Segments are therefore created with `speaker` null, and the diarization pass fills them. A meeting with no present attendees recorded still gets consistent numbering, which is strictly better than the nothing it gets today.

Cost: one LLM call per meeting, on infrastructure that already exists.

### What C1 does not do

It does not make attribution correct. It makes it *present*, using contextual inference — turn-taking, names spoken aloud, roles. It will be wrong sometimes, and segments carry `is_edited` so a human correction is never overwritten by a re-run (`SpeakerDiarizationService::applyLabels()` already respects this).

## What B1 gives C1, and what it does not

The 2026-08-02 design claimed satellite mics yield speaker identity "for free": the phone that won a window belongs to a named person, so the winner names the speaker.

**That claim requires roughly one device per person.** With two devices it yields *zones* — "nearer the laptop" versus "nearer the phone" — not names. A zone is a useful extra signal to hand the diarization prompt, and B1 should pass it, but it is not attribution.

Honest statement of the dependency: **C1 does not need B1 at all**, and B1 improves C1 only marginally. They are sequenced together because they touch the same pipeline, not because either unlocks the other. Device-derived attribution needs the N-device version, which is the expensive one and is not in this document.

## Error handling, testing, and verification

Everything the 2026-08-02 document says about testing audio without audio still applies, and `mobile/test/support/pcm.dart` now provides generators at known levels.

Specific to this work:

- **The duplicate trap.** A test must prove that two devices uploading the same chunk number both persist, and that the same device uploading the same chunk number twice still deduplicates and still answers `CHUNK_DUPLICATE` rather than erroring — the mobile outbox treats a 2xx as "drop it and move on" and a failure as "retry forever", so a new unique constraint surfacing as a 500 would wedge the queue behind a chunk that can never succeed.
- **Selection is pure.** Choosing a winner from a set of chunk rows takes no audio and no network; it should be a unit test over fixtures with confidence and level combinations, including the case where the louder chunk has the lower confidence.
- **Segment offsets.** A chunk starting at 45 s whose segments start at 0 must land at 45 s. Off-by-one-chunk errors here are invisible in a wall of text and obvious in a timeline.
- **Diarization must be faked.** No test may reach an AI provider; `SpeakerDiarizationService` takes `AIProviderInterface` in its constructor, so it substitutes cleanly.
- **Migration trap.** Per `docs`, a modified column must restate every attribute it had, and a bare `timestamp()` acquires `ON UPDATE CURRENT_TIMESTAMP` on MySQL while SQLite tests see nothing. The new columns here are plain and nullable; the unique index change is the risky part and needs checking against a MySQL-shaped database, not only the test SQLite.

### How we will know it worked

For C1, the question is whether a transcript with attributed segments produces better minutes than a wall of text. Extraction reads `full_text` either way, so this is a judgement call on drafted minutes, not a metric — and it should be checked on real meetings before any further investment in attribution.

For B1, two numbers already on the row answer the question that decides whether mixing is ever needed:

- How often the satellite's chunk beat the primary's on confidence. If it is rare, the second device is not earning its place.
- How often **both** devices produced a low-confidence chunk that a mix might have rescued — that is, chunks where the two transcripts differ substantially and neither scores well. A large number here is the argument for one-second selection; a small one closes the question.

Without collecting these, "should we mix?" stays the guess it is today.

## Shipping order

1. **C1** — segments plus end-of-session diarization. Independent of everything else, unlocks a built feature, no schema risk beyond writing rows.
2. **B1** — device dimension on chunks, satellite join, conditional transcription, selection at merge.
3. **Read the data** before deciding on mixing or on N devices. Both remain open; neither should be started on intuition.

## Honest limits

Two devices in a room is not a microphone array. It roughly halves the distance from the worst-placed speaker to the nearest microphone, which is a real gain and a bounded one. Rooms large enough that the earlier document called for participant phones will still need participant phones.

And attribution by language model is inference. It reads a transcript and guesses who spoke, informed by an attendee list. It is not diarization from the audio, it is not voice recognition, and it should never be presented to a user as though it were.
