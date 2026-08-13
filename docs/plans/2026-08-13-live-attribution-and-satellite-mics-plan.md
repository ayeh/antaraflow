# Live Speaker Attribution and Satellite Microphones Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Give live-recorded meetings a segmented, speaker-attributed transcript (C1), then let a second device record the same sitting as a satellite microphone (B1).

**Architecture:** Two independent phases against the existing live pipeline. C1 keeps the transcript segments `LiveTranscriptionJob` currently discards, writes them when the session is merged, and runs the existing `SpeakerDiarizationService` once over the result. B1 adds a device dimension to `live_transcript_chunks`, lets a second device join an active session aligned to the primary's chunk boundaries, transcribes satellite chunks only where the primary's measured level says it needs help, and picks a winner per chunk number at merge time. C1 does not depend on B1.

**Tech Stack:** Laravel 12, Pest 4, Flutter (Dart 3.12), ffmpeg, Whisper via `TranscriberFactory`.

**Design document:** `docs/plans/2026-08-13-live-attribution-and-satellite-mics-design.md`

---

## Before you start

Read the design document first. It explains *why*; this plan covers *how*. Three decisions look wrong without it:

- **Segments are written at end of session, not per chunk.** Chunks retry; writing segments per chunk leaves duplicates behind.
- **`assignSpeakers()` is deliberately not reused.** It numbers speakers from 1.5-second gaps, and live chunks are transcribed independently, so its numbering cannot be consistent across chunk boundaries.
- **B1 transcribes both devices rather than mixing them.** This reverses `2026-08-02-audio-capture-quality-design.md` Phase 3. See the design document for why.

**Conventions in this codebase you must follow:**

- Run `vendor/bin/pint --dirty --format agent` after touching any PHP file
- Run PHP tests with `php artisan test --compact`; Flutter tests with `flutter test` from `mobile/`
- Run `dart format` on Dart files you touched — **only those**, the repo is not globally format-clean
- PHP: explicit return types, constructor property promotion, curly braces always, PHPDoc over inline comments
- Never use `env()` outside `config/`
- Prefer editing existing files over creating new ones

**Baselines to measure against, not to fix:**

- `php artisan test` has **80 pre-existing failures** (browser tests). Gate on the count not increasing.
- `flutter test` has **4 pre-existing failures** in `test/features/tasks_screen_test.dart`. Same rule.

**Traps specific to this work:**

- **`ON UPDATE CURRENT_TIMESTAMP`.** A bare `$table->timestamp('x')` silently acquires it on MySQL and SQLite tests cannot see it. No task here adds a timestamp column; if you find yourself adding one, don't.
- **Modified columns lose unstated attributes.** A migration that changes a column must restate everything it had.
- **`OrganizationScope` resolves to `1=0` off the web guard.** Anything reached from a queued job or console must not rely on the tenant scope.
- **The mobile outbox retries forever on failure and drops on any 2xx.** A new database constraint surfacing as a 500 wedges a phone's upload queue permanently behind one chunk. Task 6 exists mostly to prevent this.

---

# Phase C1 — Segments and names on the live path

## Task 1: Keep the segments each chunk already produces

`LiveTranscriptionJob.php:103` reads `$result->segments[0]->speaker` and throws the rest of `$result->segments` away. There is nowhere to put them yet: the `AudioTranscription` they would belong to is not created until the session ends. So they are buffered on the chunk.

**Files:**
- Create: `database/migrations/XXXX_add_segments_to_live_transcript_chunks_table.php`
- Modify: `app/Domain/LiveMeeting/Models/LiveTranscriptChunk.php`
- Modify: `app/Domain/LiveMeeting/Jobs/LiveTranscriptionJob.php`
- Test: `tests/Feature/Domain/LiveMeeting/Jobs/LiveTranscriptionJobTest.php`

**Step 1: Write the failing test**

Add to the existing test file, following how it already fakes `TranscriberFactory`:

```php
it('keeps the segments the transcriber returned, with their own chunk clock', function () {
    $chunk = LiveTranscriptChunk::factory()->create([
        'start_time' => 45.0,
        'end_time' => 60.0,
    ]);

    // Segment times arrive relative to the chunk, always starting near zero.
    fakeTranscriber(new TranscriptionResult(
        fullText: 'we agree on the budget',
        confidence: 0.9,
        segments: [
            new TranscriptionSegmentData(text: 'we agree', startTime: 0.0, endTime: 2.5, confidence: 0.9),
            new TranscriptionSegmentData(text: 'on the budget', startTime: 2.5, endTime: 5.0, confidence: 0.88),
        ],
    ));

    (new LiveTranscriptionJob($chunk))->handle(app(TranscriberFactory::class));

    expect($chunk->fresh()->segments)->toHaveCount(2)
        ->and($chunk->fresh()->segments[0]['start_time'])->toBe(0.0)
        ->and($chunk->fresh()->segments[1]['text'])->toBe('on the budget');
});

it('replaces segments on retry rather than accumulating them', function () {
    $chunk = LiveTranscriptChunk::factory()->create(['segments' => [
        ['text' => 'stale', 'start_time' => 0.0, 'end_time' => 1.0, 'confidence' => null],
    ]]);

    fakeTranscriber(new TranscriptionResult(
        fullText: 'fresh',
        confidence: 0.9,
        segments: [new TranscriptionSegmentData(text: 'fresh', startTime: 0.0, endTime: 1.0, confidence: 0.9)],
    ));

    (new LiveTranscriptionJob($chunk))->handle(app(TranscriberFactory::class));

    expect($chunk->fresh()->segments)->toHaveCount(1)
        ->and($chunk->fresh()->segments[0]['text'])->toBe('fresh');
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter="keeps the segments"`
Expected: FAIL — unknown column `segments`.

**Step 3: Migration**

```php
Schema::table('live_transcript_chunks', function (Blueprint $table) {
    $table->json('segments')->nullable()->after('text');
});
```

Document in the migration why it is a buffer: the transcription these belong to does not exist until the session ends, and a chunk can be retried, so this column is overwritten rather than appended to.

**Step 4: Model**

Add `'segments'` to `$fillable` and `'segments' => 'array'` to `casts()`.

**Step 5: Job**

In `LiveTranscriptionJob::handle()`, replace the single-speaker line with a mapping that keeps everything, and add `'segments'` to the `update()` call:

```php
/**
 * The transcriber's segments, flattened for storage.
 *
 * Times are left on the chunk's own clock. Shifting them onto the meeting's
 * timeline needs the chunk's offset, and doing it here would bake a number
 * in that a later correction to start_time could not undo.
 *
 * @return array<int, array<string, mixed>>
 */
private function segmentsFrom(TranscriptionResult $result): array
```

Keep `'speaker' => $result->segments[0]->speaker ?? null` exactly as it is. It is near-useless with Whisper but it is not this task's job to remove it.

**Step 6: Run to verify it passes**

Run: `php artisan test --compact tests/Feature/Domain/LiveMeeting`
Expected: PASS

**Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat(live): keep the transcript segments each chunk produces"
```

---

## Task 2: Replace the chunk-sized segment with the real ones

> **Corrected during implementation.** This task was written as *create*
> segments. The merge already creates one per chunk (`LiveMeetingService.php:254`) —
> the loop sits after the transcription insert, and the original reading of this
> method stopped short of it. The work is to make those segments per-utterance
> where a chunk carries its own, and to leave the coarse one as the fallback.

**Files:**
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Feature/Domain/LiveMeeting/Services/LiveMeetingServiceTest.php`

**Step 1: Write the failing test**

```php
it('lands each chunk segment at its true position on the meeting timeline', function () {
    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->for($session, 'session')->create([
        'chunk_number' => 0, 'start_time' => 0.0, 'end_time' => 15.0,
        'status' => ChunkStatus::Completed, 'text' => 'good morning',
        'segments' => [['text' => 'good morning', 'start_time' => 1.0, 'end_time' => 3.0, 'confidence' => 0.9]],
    ]);
    LiveTranscriptChunk::factory()->for($session, 'session')->create([
        'chunk_number' => 3, 'start_time' => 45.0, 'end_time' => 60.0,
        'status' => ChunkStatus::Completed, 'text' => 'we agree',
        'segments' => [['text' => 'we agree', 'start_time' => 2.0, 'end_time' => 4.0, 'confidence' => 0.8]],
    ]);

    app(LiveMeetingService::class)->endSession($session);

    $segments = AudioTranscription::query()->latest('id')->first()->segments;

    expect($segments->pluck('start_time')->all())->toBe([1.0, 47.0])
        ->and($segments->pluck('sequence_order')->all())->toBe([0, 1]);
});

it('still produces a transcription when no chunk carried segments', function () {
    // Every recording made before this feature, and the web recorder.
    $session = LiveMeetingSession::factory()->create();
    LiveTranscriptChunk::factory()->for($session, 'session')->create([
        'chunk_number' => 0, 'status' => ChunkStatus::Completed,
        'text' => 'minutes of the meeting', 'segments' => null,
    ]);

    app(LiveMeetingService::class)->endSession($session);

    $transcription = AudioTranscription::query()->latest('id')->first();

    expect($transcription->full_text)->toBe('minutes of the meeting')
        ->and($transcription->segments)->toBeEmpty();
});
```

The second test is the important one, and it is a regression guard rather than a new behaviour: chunks with no segments of their own must keep producing exactly the one coarse segment they produce today. Every recording made before Task 1, and everything the web recorder sends, is in that case.

`full_text` is what `ExtractionService` reads (`ExtractionService.php:189`) and is unaffected either way.

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter="true position on the meeting timeline"`
Expected: FAIL — no segments created.

**Step 3: Implement**

In `mergeChunksIntoTranscription()`, after the `AudioTranscription` is created, add a private method that walks the same ordered `$completedChunks`, shifts each segment by its chunk's `start_time`, and numbers `sequence_order` across the whole session.

Insert with `insert()` rather than one `create()` per row — an hour-long sitting is several hundred segments, and this runs inside the request that ends the session.

**Step 4: Run to verify it passes**

Run: `php artisan test --compact tests/Feature/Domain/LiveMeeting`
Expected: PASS

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat(live): write transcript segments when a session is merged"
```

---

## Task 3: Name the speakers once, at the end

`SpeakerDiarizationService` exists and works. It has never run on a live meeting because there were no segments; after Task 2 there are.

**Files:**
- Create: `app/Domain/Transcription/Jobs/DiarizeTranscriptionJob.php`
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Feature/Domain/Transcription/Jobs/DiarizeTranscriptionJobTest.php`

**Step 1: Write the failing test**

Bind a fake `AIProviderInterface` returning a fixed JSON map. **No test may reach a real provider.**

```php
it('labels the segments from the attendees who were present', function () { ... });

it('leaves a hand-corrected segment alone', function () {
    // applyLabels() already filters on is_edited; this pins that it stays that way.
});

it('does nothing, and does not throw, when there are no segments', function () {
    // Every session recorded before Task 2.
});

it('abandons quietly when the organisation is over budget', function () {
    // Same shape as LiveTranscriptionJob: an LLM call that cannot be afforded
    // must not fail a meeting that has already been recorded.
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter="labels the segments"`
Expected: FAIL — class not found.

**Step 3: Implement the job**

Model it on `LiveTranscriptionJob`: guard with `OrgBudgetService`, check `AiCircuitBreaker`, set `AiUsageContext` with a new feature key `diarization`, then call `SpeakerDiarizationService::diarize()`.

Diarization is a nicety on top of a transcript that already exists. A failure here must never mark the transcription failed and must never retry aggressively — `public int $tries = 1`.

**Step 4: Dispatch it**

In `endSession()`, dispatch after the merge and independently of `ExtractMeetingDataJob`. Extraction reads `full_text` and must not wait for labelling.

```php
$transcription = $this->mergeChunksIntoTranscription($session);

if ($transcription !== null) {
    DiarizeTranscriptionJob::dispatch($transcription);
}

ExtractMeetingDataJob::dispatch($session->meeting);
```

Note `mergeChunksIntoTranscription()` already returns `?AudioTranscription` and `endSession()` currently discards it.

**Step 5: Run to verify it passes**

Run: `php artisan test --compact tests/Feature/Domain`
Expected: PASS, failure count not above baseline.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat(live): name the speakers once a sitting has ended"
```

---

## Task 4: Ship C1

Deploy and record a real sitting on a phone. Then check, by reading it:

- Do segments carry sensible times against the audio?
- Are the names plausible, and is a wrong name worse than no name for this audience?

**This is a judgement call, not a metric.** Extraction reads `full_text` either way, so nothing measurable moves. Answer it on real minutes before investing further in attribution.

---

# Phase B1 — A second device as a satellite microphone

## Task 5: Give a chunk a device

**Files:**
- Create: `database/migrations/XXXX_add_device_to_live_transcript_chunks_table.php`
- Modify: `app/Domain/LiveMeeting/Models/LiveTranscriptChunk.php`
- Test: `tests/Feature/Domain/LiveMeeting/Models/LiveTranscriptChunkTest.php`

**Step 1: Write the failing test**

```php
it('lets two devices hold the same chunk number', function () { ... });

it('refuses the same device holding the same chunk number twice', function () {
    // A real constraint, not a convention. See Step 3.
})->throws(QueryException::class);
```

**Step 2: Run to verify it fails**

Expected: FAIL — unknown column `device_id`.

**Step 3: Migration**

```php
Schema::table('live_transcript_chunks', function (Blueprint $table) {
    $table->string('device_id', 64)->nullable()->after('live_meeting_session_id');
    $table->string('role', 16)->default('primary')->after('device_id');
    $table->unique(['live_meeting_session_id', 'device_id', 'chunk_number'], 'ltc_session_device_chunk_unique');
});
```

`device_id` is nullable because the web recorder sends none — a null device is the session's one primary, which is exactly today's behaviour.

**Note in the migration** that no unique constraint existed before this: deduplication has been a check-then-insert in `findExistingChunk()` with nothing behind it (`2026_03_08_085523_create_live_transcript_chunks_table.php:28` is a plain index). Adding the device dimension is the moment to make it real.

**Check this against MySQL, not only the test SQLite.** A unique index over a nullable column behaves differently across engines, and prod is MySQL on DirectAdmin — run it there before it ships.

**Step 4: Run to verify it passes**, then commit.

---

## Task 6: Deduplicate per device, without wedging a phone

The most dangerous task in the plan. `findExistingChunk()` (`LiveMeetingService.php:100`) and `getResumeState()` (`LiveMeetingService.php:173`) both currently key on the session alone.

**Files:**
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Modify: `app/Domain/API/Controllers/Mobile/V1/LiveSessionController.php`
- Test: `tests/Feature/Domain/API/Mobile/LiveSessionTest.php`

**Step 1: Write the failing tests**

```php
it('accepts the same chunk number from a second device', function () {
    // Both persist. Getting this backwards throws the satellite's audio away
    // while answering 2xx, which the outbox reads as success.
});

it('still answers CHUNK_DUPLICATE when one device sends a chunk twice', function () {
    // Must be a 2xx with code CHUNK_DUPLICATE, never a 500 from the new
    // constraint: the outbox retries a failure forever and would wedge the
    // whole queue behind a chunk that can never succeed.
});

it('answers CHUNK_DUPLICATE rather than a database error on a concurrent retry', function () {
    // Insert the row directly, then post it, simulating the race the
    // check-then-insert has always had and the constraint now surfaces.
});

it('resumes each device from its own place in the numbering', function () { ... });
```

**Step 2: Run to verify they fail.**

**Step 3: Implement**

- `findExistingChunk(LiveMeetingSession $session, int $chunkNumber, ?string $deviceId = null)`
- `getResumeState(LiveMeetingSession $session, ?string $deviceId = null)` — filter chunks by device before computing `next_chunk_number` and `missing_chunks`. The `stats` block stays session-wide; it describes the sitting, not a device.
- Catch the unique-violation in `processChunk()` and return the existing row, so the race resolves as a duplicate rather than a 500.

Both new parameters default to null so `LiveMeetingController` (web) needs no change.

**Step 4: Run to verify they pass**, then commit.

---

## Task 7: Carry the device on the chunk upload

**Files:**
- Modify: `app/Domain/API/Controllers/Mobile/V1/LiveSessionController.php`
- Modify: `mobile/lib/data/repositories/live_repository.dart`
- Modify: `mobile/lib/features/recorder/chunk_outbox.dart`
- Test: `tests/Feature/Domain/API/Mobile/LiveSessionTest.php`

Validate `device_id` as `['sometimes', 'string', 'max:64']` and `role` as `['sometimes', Rule::in(['primary', 'satellite'])]`, and pass both through `processChunk()`.

The app already holds a stable per-installation identifier — `SecureStore.deviceId()`, sent today when authenticating (`mobile/lib/data/repositories/auth_repository.dart:28`). Read it once when the recorder session opens rather than per chunk: it is backed by `flutter_secure_storage`, and hitting the keychain every fifteen seconds for the length of a meeting is work for nothing.

**Also update the idempotency key.** `LiveRepository.uploadChunk()` sends `idempotency-key: chunk-$sessionId-$chunkNumber`. `MobileIdempotency` scopes its cache key by user id, path and key, so two *different* people's devices are already safe — but one person recording on two of their own devices is not, and that is a realistic setup. Their satellite's chunk 12 would be answered from the cached response to the primary's chunk 12, and the audio would never reach `processChunk()` at all.

It must become `chunk-$sessionId-$deviceId-$chunkNumber`. Add a test at this task that posts the same chunk number from two devices belonging to the same user and asserts two rows exist — without it, the bug is invisible, because every response looks like success.

Commit.

---

## Task 8: Let a second device join, aligned to the primary

**Files:**
- Modify: `app/Domain/API/Controllers/Mobile/V1/LiveSessionController.php`
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Feature/Domain/API/Mobile/LiveSessionTest.php`

A satellite starting a session on a meeting that already has one gets today's 409 `SESSION_ALREADY_ACTIVE` with `resume.next_chunk_number`. That is nearly what it needs, and it is missing one number.

`next_chunk_number` says where the boundary is. It does not say how far past that boundary the sitting already is — the satellite is joining mid-chunk and needs to know how long to wait.

Add `seconds_into_chunk` to the resume payload, computed from `started_at`, `paused_at` and the session's configured chunk length.

**The satellite discards its partial first window rather than uploading a short chunk.** A short chunk numbered N covers only part of the window the primary's chunk N covers, and selecting it would silently drop the first seconds of that window from the transcript. Throwing away at most fifteen seconds of satellite audio at join time is the cheaper mistake. *(This refines the design document, which said the first cut should be short.)*

Network latency puts the satellite's boundaries a few hundred milliseconds off the primary's. That is fine and should be stated in the code: B1 never mixes samples, so alignment only has to be good enough to agree on which fifteen-second window a chunk belongs to.

Commit.

---

## Task 9: Align the recorder to somebody else's boundaries

**Files:**
- Modify: `mobile/lib/features/recorder/audio_chunker.dart`
- Modify: `mobile/lib/domain/models/live_session.dart`
- Test: `mobile/test/features/audio_chunker_test.dart`

**Step 1: Write the failing test**

```dart
test('a satellite discards audio until the primary cuts its next chunk', () async {
  final chunker = AudioChunker();
  final cut = <AudioChunk>[];
  chunker.chunks.listen(cut.add);

  // The primary is seven seconds into chunk 2.
  chunker.prepare(
    scratch,
    fromChunk: 3,
    alreadyRecorded: const Duration(seconds: 45),
    discardFirst: const Duration(seconds: 8),
  );

  chunker.receive(seconds(8));
  await chunker.settled;
  await pumpEventQueue();
  expect(cut, isEmpty, reason: 'this audio belongs to a window already half gone');

  chunker.receive(seconds(15));
  await chunker.settled;
  await pumpEventQueue();

  expect(cut.single.number, 3);
  expect(cut.single.start, const Duration(seconds: 45));
});
```

**Step 2: Run to verify it fails.**

**Step 3: Implement**

`discardFirst` consumes that many bytes before the buffer starts filling. It must not advance `_samplesWritten`, or the clock and every chunk boundary after it move.

Feed the discarded audio to `RoomLevel` anyway — the placement check should start listening the moment the microphone opens, not fifteen seconds later.

**Step 4: Verify**

Run: `cd mobile && flutter test test/features/audio_chunker_test.dart && flutter analyze`

Commit.

---

## Task 10: The join, from the user's side

**Files:**
- Modify: `mobile/lib/features/recorder/recorder_controller.dart`
- Modify: `mobile/lib/features/recorder/recorder_screen.dart`
- Modify: `mobile/lib/features/recorder/start_recording_sheet.dart`
- Modify: `mobile/lib/l10n/app_en.arb`, `mobile/lib/l10n/app_ms.arb`
- Test: `mobile/test/features/recorder_test.dart`

Today, a second device hitting Record on a meeting that is already being recorded silently resumes the session and fights the primary over chunk numbering. That is a live bug this task closes.

It should instead ask: **"This sitting is already being recorded. Add this phone as an extra microphone?"** with the honest consequence stated — the audio goes to the organisation, the same as the recording already running.

The recorder screen must say which role it is in for the whole sitting. A phone acting as a satellite is not recording the meeting; it is helping. If the user cannot tell those apart, they will stop the wrong one.

Both ARB files. Malay is the primary audience.

Commit.

---

## Task 11: Spend the second transcription only where it is needed

**Files:**
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Feature/Domain/LiveMeeting/Services/LiveMeetingServiceTest.php`

A satellite chunk is always stored. It is only transcribed when the primary's own measurement says the primary struggled:

- the primary's chunk for that number is missing or not `Completed`, or
- its `speech_dbfs` is below `RoomLevel.faintBelow` (-45.0), or
- its `confidence` is below a threshold in config

Otherwise the satellite chunk is stored with status `Skipped` and never sent to the transcriber. The audio is kept, so this decision can be revisited later against audio we still hold — put that in the comment, because a future reader will assume skipped means discarded.

A satellite chunk arriving **before** the primary's cannot be judged yet. Leave it pending and let the primary's arrival release it; do not guess.

Add `ChunkStatus::Skipped` to the enum — there is no exhaustive `match` over it anywhere, so a new case breaks no call site, but there are 17 references worth reading before adding one.

Then check every `where('status', ...)` on chunks. `mergeChunksIntoTranscription()` filters on `Completed` and ignores skipped rows correctly, but two things nearby do not:

- the **dropped-chunk warning** counts `!= Completed`, so it would log every skipped satellite chunk as lost audio, and
- `LiveTranscriptIncomplete` fires from that same count, which means it would **tell the user their transcript has holes in it after every meeting that used a satellite**.

That second one is the real hazard: it turns a working feature into a false alarm on the most alarming subject this product has. Neither is optional.

Commit.

---

## Task 12: Pick a winner per chunk

**Files:**
- Create: `app/Domain/LiveMeeting/Support/ChunkSelector.php`
- Modify: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Unit/Domain/LiveMeeting/ChunkSelectorTest.php`

Selection is pure: it takes rows and returns one. No audio, no network, no database. Test it as a unit.

```php
it('prefers the higher confidence', ...);
it('prefers the much louder chunk when confidence is close', ...);
it('takes the only candidate when one device produced nothing', ...);
it('never picks a chunk that was skipped or failed', ...);
it('is stable when two chunks are identical', ...);  // primary wins ties
```

Then group `$completedChunks` by `chunk_number` in `mergeChunksIntoTranscription()`, run the selector, and build both `full_text` and the segments from the winners.

Record which device won each chunk in `provider_metadata` on the transcription. Task 14 needs it, and after the fact there is no way to recover it.

Commit.

---

## Task 13: Ship B1

Two devices, one real meeting, one room. Confirm by listening and reading:

- The satellite's chunks land in the right windows — a transcript that reads interleaved means alignment is wrong, and that is the failure to watch for.
- Killing the satellite mid-meeting changes nothing about the primary's recording.
- The primary alone still behaves exactly as before.

---

## Task 14: Read the data before deciding on mixing

Do not start per-second mixing on intuition. After enough sittings, answer:

1. **How often did the satellite win?** Rare means the second device is not earning its place, and the answer is better placement, not more machinery.
2. **How often were both chunks poor and different?** Chunks where neither device scored well and the two transcripts disagree substantially are the ones a mix might have rescued. A large number is the argument for per-second selection and crossfades. A small one closes the question and the 2026-08-02 Phase 3 design can be retired.

Write the answer down. "Should we mix?" has been a guess through two design documents; this is what ends that.

---

## Honest limits

C1 gives attribution by language model. It reads a transcript and infers who spoke from turn-taking, names said aloud, and roles. It is not diarization from audio and it is not voice recognition, and it must never be presented to a user as though it were.

B1 is two microphones, not an array. It roughly halves the distance from the worst-placed speaker to the nearest microphone. Rooms large enough to need a phone per participant still need a phone per participant, and that is not in this plan.
