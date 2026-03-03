# Browser Recording Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the "Coming Soon" browser recording placeholder with a fully functional audio recorder using native MediaRecorder API + Alpine.js state machine, with live waveform visualization, hybrid upload strategy, and IndexedDB recovery.

**Architecture:** Alpine.js component registered via `Alpine.data()` in a separate JS file. Uses MediaRecorder API for recording, Web Audio API AnalyserNode for live waveform on Canvas, IndexedDB for blob safety backup. Backend gets a new `AudioChunkController` for progressive chunked uploads (recordings ≥ 5 min). Short recordings use existing `TranscriptionController::store()`.

**Tech Stack:** MediaRecorder API, Web Audio API, Canvas 2D, Alpine.js, IndexedDB, Laravel controllers/services, Pest tests

**Design Doc:** `docs/plans/2026-03-03-browser-recording-design.md`

---

## Task 1: Add chunk storage methods to AudioStorageService

**Files:**
- Modify: `app/Domain/Transcription/Services/AudioStorageService.php`
- Test: `tests/Feature/Transcription/AudioStorageServiceTest.php`

**Step 1: Write failing tests for chunk storage**

Check existing test file first. Create/add tests for `storeChunk()`, `mergeChunks()`, `deleteChunks()`:

```php
it('stores an audio chunk to the correct path', function () {
    $file = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');
    $service = app(AudioStorageService::class);

    $path = $service->storeChunk($file, organizationId: 1, sessionId: 'abc-123', chunkIndex: 0);

    expect($path)->toContain('organizations/1/audio/chunks/abc-123/');
    expect(Storage::disk('local')->exists($path))->toBeTrue();
});

it('merges audio chunks into a single file', function () {
    $service = app(AudioStorageService::class);
    $sessionId = 'merge-test';
    $orgId = 1;

    // Store 3 fake chunks
    for ($i = 0; $i < 3; $i++) {
        $file = UploadedFile::fake()->create("chunk_{$i}.webm", 50, 'audio/webm');
        $service->storeChunk($file, $orgId, $sessionId, $i);
    }

    $mergedPath = $service->mergeChunks($orgId, $sessionId, 'audio/webm');

    expect($mergedPath)->toContain('organizations/1/audio/');
    expect($mergedPath)->not->toContain('chunks/');
    expect(Storage::disk('local')->exists($mergedPath))->toBeTrue();
});

it('deletes all chunks for a session', function () {
    $service = app(AudioStorageService::class);
    $sessionId = 'delete-test';

    $file = UploadedFile::fake()->create('chunk.webm', 50, 'audio/webm');
    $service->storeChunk($file, 1, $sessionId, 0);

    $service->deleteChunks(1, $sessionId);

    $chunkDir = "organizations/1/audio/chunks/{$sessionId}";
    expect(Storage::disk('local')->files($chunkDir))->toBeEmpty();
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=AudioStorageService`
Expected: FAIL — methods don't exist yet

**Step 3: Implement chunk methods**

Add to `AudioStorageService`:

```php
public function storeChunk(UploadedFile $file, int $organizationId, string $sessionId, int $chunkIndex): string
{
    $path = "organizations/{$organizationId}/audio/chunks/{$sessionId}";

    return $file->storeAs($path, "chunk_{$chunkIndex}." . $file->getClientOriginalExtension(), 'local');
}

public function mergeChunks(int $organizationId, string $sessionId, string $mimeType): string
{
    $chunkDir = "organizations/{$organizationId}/audio/chunks/{$sessionId}";
    $disk = Storage::disk('local');
    $files = collect($disk->files($chunkDir))->sort()->values();

    $extension = match (true) {
        str_contains($mimeType, 'webm') => 'webm',
        str_contains($mimeType, 'mp4') => 'mp4',
        str_contains($mimeType, 'ogg') => 'ogg',
        default => 'webm',
    };

    $mergedFilename = 'recording_' . now()->format('Ymd_His') . '.' . $extension;
    $mergedPath = "organizations/{$organizationId}/audio/{$mergedFilename}";

    $mergedContent = '';
    foreach ($files as $file) {
        $mergedContent .= $disk->get($file);
    }

    $disk->put($mergedPath, $mergedContent);

    $this->deleteChunks($organizationId, $sessionId);

    return $mergedPath;
}

public function deleteChunks(int $organizationId, string $sessionId): void
{
    $chunkDir = "organizations/{$organizationId}/audio/chunks/{$sessionId}";
    $disk = Storage::disk('local');

    foreach ($disk->files($chunkDir) as $file) {
        $disk->delete($file);
    }

    $disk->deleteDirectory($chunkDir);
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=AudioStorageService`
Expected: PASS

**Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 6: Commit**

```bash
git add app/Domain/Transcription/Services/AudioStorageService.php tests/Feature/Transcription/AudioStorageServiceTest.php
git commit -m "feat: add chunk storage methods to AudioStorageService"
```

---

## Task 2: Create AudioChunkController with routes

**Files:**
- Create: `app/Domain/Transcription/Controllers/AudioChunkController.php`
- Create: `app/Domain/Transcription/Requests/StoreAudioChunkRequest.php`
- Modify: `routes/web.php` (add chunk routes inside existing meetings group)
- Test: `tests/Feature/Transcription/AudioChunkControllerTest.php`

**Step 1: Write failing tests**

Create test file. Use `php artisan make:test --pest Transcription/AudioChunkControllerTest`.

```php
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->user->organization_id,
    ]);
    $this->actingAs($this->user);
});

it('stores an audio chunk', function () {
    $file = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $response = $this->postJson(
        route('meetings.audio-chunks.store', $this->meeting),
        [
            'chunk' => $file,
            'session_id' => 'test-session-123',
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ]
    );

    $response->assertOk();
    $response->assertJsonStructure(['message', 'chunk_index']);
});

it('validates chunk upload request', function () {
    $response = $this->postJson(
        route('meetings.audio-chunks.store', $this->meeting),
        []
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['chunk', 'session_id', 'chunk_index']);
});

it('finalizes chunks into a single transcription', function () {
    $sessionId = 'finalize-test';

    // Upload 2 chunks first
    for ($i = 0; $i < 2; $i++) {
        $file = UploadedFile::fake()->create("chunk_{$i}.webm", 50, 'audio/webm');
        $this->postJson(
            route('meetings.audio-chunks.store', $this->meeting),
            [
                'chunk' => $file,
                'session_id' => $sessionId,
                'chunk_index' => $i,
                'mime_type' => 'audio/webm',
            ]
        );
    }

    $response = $this->postJson(
        route('meetings.audio-chunks.finalize', $this->meeting),
        [
            'session_id' => $sessionId,
            'mime_type' => 'audio/webm',
            'duration_seconds' => 125,
            'language' => 'en',
        ]
    );

    $response->assertOk();
    $response->assertJsonStructure(['message', 'transcription']);
    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'duration_seconds' => 125,
    ]);
});

it('deletes chunks when recording is cancelled', function () {
    $sessionId = 'cancel-test';
    $file = UploadedFile::fake()->create('chunk.webm', 50, 'audio/webm');

    $this->postJson(
        route('meetings.audio-chunks.store', $this->meeting),
        [
            'chunk' => $file,
            'session_id' => $sessionId,
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ]
    );

    $response = $this->deleteJson(
        route('meetings.audio-chunks.destroy', $this->meeting),
        ['session_id' => $sessionId]
    );

    $response->assertOk();
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=AudioChunkController`
Expected: FAIL — controller/routes don't exist

**Step 3: Create FormRequest**

Run: `php artisan make:request --no-interaction Domain/Transcription/Requests/StoreAudioChunkRequest`

If artisan doesn't support the namespace, create manually at `app/Domain/Transcription/Requests/StoreAudioChunkRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'chunk' => ['required', 'file', 'max:51200'],
            'session_id' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'mime_type' => ['required', 'string'],
        ];
    }
}
```

**Step 4: Create AudioChunkController**

Create `app/Domain/Transcription/Controllers/AudioChunkController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Requests\StoreAudioChunkRequest;
use App\Domain\Transcription\Services\AudioStorageService;
use App\Domain\Transcription\Services\TranscriptionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AudioChunkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AudioStorageService $audioStorageService,
        private TranscriptionService $transcriptionService,
    ) {}

    public function store(StoreAudioChunkRequest $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $this->audioStorageService->storeChunk(
            $request->file('chunk'),
            $meeting->organization_id,
            $request->validated('session_id'),
            $request->validated('chunk_index'),
        );

        return response()->json([
            'message' => 'Chunk uploaded.',
            'chunk_index' => $request->validated('chunk_index'),
        ]);
    }

    public function finalize(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'uuid'],
            'mime_type' => ['required', 'string'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        $mergedPath = $this->audioStorageService->mergeChunks(
            $meeting->organization_id,
            $validated['session_id'],
            $validated['mime_type'],
        );

        $transcription = $this->transcriptionService->createFromBrowserRecording(
            $mergedPath,
            $meeting,
            $request->user(),
            $validated['mime_type'],
            $validated['duration_seconds'],
            $validated['language'] ?? 'en',
        );

        return response()->json([
            'message' => 'Recording finalized and transcription started.',
            'transcription' => $transcription,
        ]);
    }

    public function destroy(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'uuid'],
        ]);

        $this->audioStorageService->deleteChunks(
            $meeting->organization_id,
            $validated['session_id'],
        );

        return response()->json(['message' => 'Chunks deleted.']);
    }
}
```

**Step 5: Add `createFromBrowserRecording` to TranscriptionService**

Add to `app/Domain/Transcription/Services/TranscriptionService.php`:

```php
public function createFromBrowserRecording(
    string $filePath,
    MinutesOfMeeting $mom,
    User $user,
    string $mimeType,
    int $durationSeconds,
    string $language = 'en',
): AudioTranscription {
    $disk = Storage::disk('local');
    $fileSize = $disk->size($filePath);
    $filename = basename($filePath);

    $transcription = AudioTranscription::query()->create([
        'minutes_of_meeting_id' => $mom->id,
        'uploaded_by' => $user->id,
        'original_filename' => $filename,
        'file_path' => $filePath,
        'mime_type' => $mimeType,
        'file_size' => $fileSize,
        'duration_seconds' => $durationSeconds,
        'language' => $language,
        'status' => TranscriptionStatus::Pending,
    ]);

    $mom->inputs()->create([
        'type' => InputType::BrowserRecording,
        'source_type' => AudioTranscription::class,
        'source_id' => $transcription->id,
    ]);

    ProcessTranscriptionJob::dispatch($transcription);

    return $transcription;
}
```

Add `use Illuminate\Support\Facades\Storage;` to imports.

**Step 6: Add routes**

In `routes/web.php`, inside the existing `Route::prefix('meetings/{meeting}')` group, add:

```php
Route::post('audio-chunks', [AudioChunkController::class, 'store'])->name('audio-chunks.store');
Route::post('audio-chunks/finalize', [AudioChunkController::class, 'finalize'])->name('audio-chunks.finalize');
Route::delete('audio-chunks', [AudioChunkController::class, 'destroy'])->name('audio-chunks.destroy');
```

Add the import at the top of web.php:

```php
use App\Domain\Transcription\Controllers\AudioChunkController;
```

**Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter=AudioChunkController`
Expected: PASS

**Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 9: Commit**

```bash
git add app/Domain/Transcription/Controllers/AudioChunkController.php \
  app/Domain/Transcription/Requests/StoreAudioChunkRequest.php \
  app/Domain/Transcription/Services/TranscriptionService.php \
  routes/web.php \
  tests/Feature/Transcription/AudioChunkControllerTest.php
git commit -m "feat: add AudioChunkController for chunked browser recording uploads"
```

---

## Task 3: Modify TranscriptionController to accept JSON responses

**Files:**
- Modify: `app/Domain/Transcription/Controllers/TranscriptionController.php`
- Test: `tests/Feature/Transcription/TranscriptionControllerTest.php`

The existing `store()` method returns a redirect. The browser recorder needs a JSON response when uploading short recordings (< 5 min). Modify to return JSON when `Accept: application/json` header is sent.

**Step 1: Write failing test**

Add to existing test file:

```php
it('returns json response when accept header is json', function () {
    $user = User::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $user->organization_id,
    ]);
    $file = UploadedFile::fake()->create('recording.webm', 500, 'audio/webm');

    $response = $this->actingAs($user)
        ->postJson(route('meetings.transcriptions.store', $meeting), [
            'audio' => $file,
            'language' => 'en',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['message', 'transcription']);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="returns json response"`
Expected: FAIL — returns redirect, not JSON

**Step 3: Modify store method**

Update `TranscriptionController::store()`:

```php
public function store(UploadAudioRequest $request, MinutesOfMeeting $meeting): RedirectResponse|JsonResponse
{
    $this->authorize('update', $meeting);

    $transcription = $this->transcriptionService->upload(
        $request->file('audio'),
        $meeting,
        $request->user(),
        $request->validated('language', 'en'),
    );

    if ($request->wantsJson()) {
        return response()->json([
            'message' => 'Audio uploaded and transcription started.',
            'transcription' => $transcription,
        ]);
    }

    return redirect()->route('meetings.show', $meeting)
        ->with('success', 'Audio uploaded and transcription started.');
}
```

Add `use Illuminate\Http\JsonResponse;` import.

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TranscriptionController`
Expected: PASS (all existing + new)

**Step 5: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Transcription/Controllers/TranscriptionController.php tests/Feature/Transcription/TranscriptionControllerTest.php
git commit -m "feat: support JSON responses in TranscriptionController::store"
```

---

## Task 4: Create stale chunk cleanup command

**Files:**
- Create: `app/Domain/Transcription/Commands/CleanupStaleChunksCommand.php`
- Modify: `routes/console.php` (schedule the command)
- Test: `tests/Feature/Transcription/CleanupStaleChunksCommandTest.php`

**Step 1: Write failing test**

```php
use Illuminate\Support\Facades\Storage;

it('deletes chunk directories older than 24 hours', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    // Create a "stale" chunk dir (simulate old file by checking modified time)
    $disk->put('organizations/1/audio/chunks/old-session/chunk_0.webm', 'data');

    $this->artisan('transcription:cleanup-chunks')
        ->assertSuccessful();
});

it('preserves recent chunk directories', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('organizations/1/audio/chunks/recent-session/chunk_0.webm', 'data');

    $this->artisan('transcription:cleanup-chunks')
        ->assertSuccessful();

    // Recent chunks should still exist
    expect($disk->exists('organizations/1/audio/chunks/recent-session/chunk_0.webm'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CleanupStaleChunks`
Expected: FAIL — command doesn't exist

**Step 3: Create command**

Run: `php artisan make:command --no-interaction Domain/Transcription/Commands/CleanupStaleChunksCommand` or create manually:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupStaleChunksCommand extends Command
{
    protected $signature = 'transcription:cleanup-chunks {--hours=24 : Hours after which chunks are considered stale}';

    protected $description = 'Delete audio recording chunks older than the specified threshold';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('local');
        $threshold = Carbon::now()->subHours($hours);
        $deleted = 0;

        // Find all organization audio chunk directories
        foreach ($disk->directories('/') as $orgDir) {
            $chunkBase = "{$orgDir}/audio/chunks";

            if (! $disk->exists($chunkBase)) {
                continue;
            }

            foreach ($disk->directories($chunkBase) as $sessionDir) {
                $files = $disk->files($sessionDir);

                if (empty($files)) {
                    $disk->deleteDirectory($sessionDir);
                    $deleted++;

                    continue;
                }

                $lastModified = collect($files)
                    ->map(fn (string $file) => $disk->lastModified($file))
                    ->max();

                if (Carbon::createFromTimestamp($lastModified)->lt($threshold)) {
                    $disk->deleteDirectory($sessionDir);
                    $deleted++;
                }
            }
        }

        $this->info("Cleaned up {$deleted} stale chunk directories.");

        return self::SUCCESS;
    }
}
```

**Step 4: Schedule the command**

In `routes/console.php`, add:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('transcription:cleanup-chunks')->hourly();
```

**Step 5: Run tests, Pint, commit**

```bash
php artisan test --compact --filter=CleanupStaleChunks
vendor/bin/pint --dirty --format agent
git add app/Domain/Transcription/Commands/CleanupStaleChunksCommand.php routes/console.php tests/Feature/Transcription/CleanupStaleChunksCommandTest.php
git commit -m "feat: add scheduled command to cleanup stale audio chunks"
```

---

## Task 5: Create Alpine.js audio recorder component

**Files:**
- Create: `resources/js/audio-recorder.js`
- Modify: `resources/js/app.js` (import and register)

This is the core frontend component — state machine, MediaRecorder, Web Audio, IndexedDB, upload management.

**Step 1: Create the Alpine component file**

Create `resources/js/audio-recorder.js` with the following structure. This file exports an Alpine data function:

```javascript
export default function audioRecorder(config) {
    return {
        // Config (passed from blade)
        uploadUrl: config.uploadUrl,
        chunkUrl: config.chunkUrl,
        finalizeUrl: config.finalizeUrl,
        cancelUrl: config.cancelUrl,
        meetingId: config.meetingId,

        // State machine
        state: 'idle', // idle, requesting_permission, ready, countdown, recording, paused, stopping, processing, uploading, complete, error

        // Recording data
        mediaRecorder: null,
        mediaStream: null,
        audioContext: null,
        analyserNode: null,
        chunks: [],
        sessionId: null,
        mimeType: null,
        recordedBlob: null,

        // UI state
        timer: 0,
        timerInterval: null,
        countdownValue: 3,
        countdownInterval: null,
        uploadProgress: 0,
        errorMessage: '',
        successMessage: '',
        canvasContext: null,
        animationFrame: null,
        showCountdown: true,

        // Chunk upload tracking
        chunkIndex: 0,
        isLongRecording: false,
        chunkInterval: null,
        uploadedChunks: 0,

        // Recovery
        hasPendingRecovery: false,
        recoveryTimestamp: null,

        // Computed
        get formattedTimer() {
            const hours = Math.floor(this.timer / 3600);
            const mins = Math.floor((this.timer % 3600) / 60);
            const secs = this.timer % 60;
            if (hours > 0) {
                return `${hours}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }
            return `${mins}:${String(secs).padStart(2, '0')}`;
        },

        get isRecording() {
            return this.state === 'recording';
        },

        get isPaused() {
            return this.state === 'paused';
        },

        get isProcessing() {
            return ['stopping', 'processing', 'uploading'].includes(this.state);
        },

        // ── Lifecycle ──────────────────────────────────
        init() {
            this.detectMimeType();
            this.checkPendingRecovery();
            this.checkExistingPermission();

            // Setup canvas after DOM ready
            this.$nextTick(() => {
                const canvas = this.$refs.waveformCanvas;
                if (canvas) {
                    this.canvasContext = canvas.getContext('2d');
                }
            });
        },

        destroy() {
            this.cleanup();
        },

        // ── MIME Type Detection ────────────────────────
        detectMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
            ];

            for (const type of types) {
                if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) {
                    this.mimeType = type;
                    return;
                }
            }
            this.mimeType = 'audio/webm';
        },

        // ── Permission Handling ────────────────────────
        async checkExistingPermission() {
            try {
                if (navigator.permissions?.query) {
                    const result = await navigator.permissions.query({ name: 'microphone' });
                    if (result.state === 'granted') {
                        await this.setupStream();
                        this.state = 'ready';
                    }
                }
            } catch {
                // Firefox doesn't support microphone permission query
            }
        },

        async requestPermission() {
            this.state = 'requesting_permission';
            this.errorMessage = '';

            try {
                await this.setupStream();
                this.state = 'ready';
            } catch (err) {
                this.handlePermissionError(err);
            }
        },

        handlePermissionError(err) {
            this.state = 'error';

            if (err.name === 'NotAllowedError') {
                this.errorMessage = 'Microphone access denied. Please allow microphone access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                this.errorMessage = 'No microphone found. Please connect a microphone and try again.';
            } else if (err.name === 'NotReadableError') {
                this.errorMessage = 'Microphone is in use by another application. Please close it and try again.';
            } else {
                this.errorMessage = 'Could not access microphone. Please try again.';
            }
        },

        // ── Stream & Audio Context Setup ───────────────
        async setupStream() {
            this.mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });

            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.audioContext.createMediaStreamSource(this.mediaStream);
            this.analyserNode = this.audioContext.createAnalyser();
            this.analyserNode.fftSize = 2048;
            source.connect(this.analyserNode);

            this.drawWaveform();
        },

        // ── Waveform Visualization ─────────────────────
        drawWaveform() {
            if (!this.analyserNode || !this.canvasContext) {
                this.animationFrame = requestAnimationFrame(() => this.drawWaveform());
                return;
            }

            const canvas = this.$refs.waveformCanvas;
            if (!canvas) return;

            const ctx = this.canvasContext;
            const width = canvas.width;
            const height = canvas.height;
            const bufferLength = this.analyserNode.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            this.analyserNode.getByteTimeDomainData(dataArray);

            ctx.fillStyle = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-gray-50')?.trim() || '#f9fafb';

            if (document.documentElement.classList.contains('dark')) {
                ctx.fillStyle = 'rgba(55, 65, 81, 0.3)';
            }

            ctx.fillRect(0, 0, width, height);

            // Draw waveform
            const isActive = ['recording', 'ready'].includes(this.state);
            ctx.lineWidth = 2;

            if (this.state === 'recording') {
                ctx.strokeStyle = '#ef4444'; // red-500
            } else if (this.state === 'ready') {
                ctx.strokeStyle = '#22c55e'; // green-500
            } else if (this.state === 'paused') {
                ctx.strokeStyle = '#f59e0b'; // amber-500
            } else {
                ctx.strokeStyle = '#9ca3af'; // gray-400
            }

            ctx.beginPath();
            const sliceWidth = width / bufferLength;
            let x = 0;

            for (let i = 0; i < bufferLength; i++) {
                const v = dataArray[i] / 128.0;
                const y = (v * height) / 2;

                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
                x += sliceWidth;
            }

            ctx.lineTo(width, height / 2);
            ctx.stroke();

            if (!['idle', 'complete', 'error'].includes(this.state)) {
                this.animationFrame = requestAnimationFrame(() => this.drawWaveform());
            }
        },

        // ── Recording Controls ─────────────────────────
        async startRecording() {
            if (this.state === 'idle') {
                await this.requestPermission();
                if (this.state !== 'ready') return;
            }

            if (this.showCountdown) {
                this.startCountdown();
            } else {
                this.beginRecording();
            }
        },

        startCountdown() {
            this.state = 'countdown';
            this.countdownValue = 3;

            this.countdownInterval = setInterval(() => {
                this.countdownValue--;
                if (this.countdownValue <= 0) {
                    clearInterval(this.countdownInterval);
                    this.beginRecording();
                }
            }, 1000);
        },

        beginRecording() {
            this.chunks = [];
            this.chunkIndex = 0;
            this.uploadedChunks = 0;
            this.timer = 0;
            this.isLongRecording = false;
            this.sessionId = crypto.randomUUID();
            this.errorMessage = '';

            this.mediaRecorder = new MediaRecorder(this.mediaStream, {
                mimeType: this.mimeType,
            });

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    this.chunks.push(e.data);

                    // For long recordings, upload chunk immediately
                    if (this.isLongRecording) {
                        this.uploadChunk(e.data, this.chunkIndex);
                        this.chunkIndex++;
                    }
                }
            };

            this.mediaRecorder.onstop = () => {
                this.handleRecordingStop();
            };

            this.mediaRecorder.onerror = (e) => {
                console.error('MediaRecorder error:', e);
                this.state = 'error';
                this.errorMessage = 'Recording failed. Please try again.';
                this.cleanup();
            };

            // Start recording (no timeslice initially - wait until 5 min mark)
            this.mediaRecorder.start();
            this.state = 'recording';
            this.playBeep(300, 200); // Start beep

            // Start timer
            this.timerInterval = setInterval(() => {
                this.timer++;

                // Switch to chunked mode at 5 minutes
                if (this.timer === 300 && !this.isLongRecording) {
                    this.switchToChunkedMode();
                }
            }, 1000);

            // Restart waveform drawing
            this.drawWaveform();
        },

        switchToChunkedMode() {
            this.isLongRecording = true;

            // Stop and restart with timeslice for chunked recording
            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.stop();

                // Create new recorder with timeslice
                this.mediaRecorder = new MediaRecorder(this.mediaStream, {
                    mimeType: this.mimeType,
                });

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        this.uploadChunk(e.data, this.chunkIndex);
                        this.chunkIndex++;
                    }
                };

                this.mediaRecorder.onstop = () => {
                    this.handleRecordingStop();
                };

                // Upload initial chunks that were collected before switch
                const initialBlob = new Blob(this.chunks, { type: this.mimeType });
                this.uploadChunk(initialBlob, 0);
                this.chunkIndex = 1;
                this.chunks = [];

                this.mediaRecorder.start(30000); // 30 second timeslice
            }
        },

        pauseRecording() {
            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.pause();
                this.state = 'paused';
                clearInterval(this.timerInterval);
            }
        },

        resumeRecording() {
            if (this.mediaRecorder?.state === 'paused') {
                this.mediaRecorder.resume();
                this.state = 'recording';

                this.timerInterval = setInterval(() => {
                    this.timer++;
                    if (this.timer === 300 && !this.isLongRecording) {
                        this.switchToChunkedMode();
                    }
                }, 1000);

                this.drawWaveform();
            }
        },

        async stopRecording() {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') return;

            this.state = 'stopping';
            this.playBeep(500, 150); // Stop beep
            clearInterval(this.timerInterval);

            // Timeout fallback for Safari onstop bug
            const stopTimeout = setTimeout(() => {
                if (this.state === 'stopping') {
                    console.warn('MediaRecorder onstop timeout - using fallback');
                    this.handleRecordingStop();
                }
            }, 3000);

            this.mediaRecorder._stopTimeout = stopTimeout;
            this.mediaRecorder.stop();
        },

        async handleRecordingStop() {
            if (this.mediaRecorder?._stopTimeout) {
                clearTimeout(this.mediaRecorder._stopTimeout);
            }

            if (this.state !== 'stopping') return; // Prevent double-fire

            this.state = 'processing';

            if (this.isLongRecording) {
                // Finalize chunked upload
                await this.finalizeChunkedUpload();
            } else {
                // Single blob upload
                this.recordedBlob = new Blob(this.chunks, { type: this.mimeType });

                if (this.recordedBlob.size === 0) {
                    this.state = 'error';
                    this.errorMessage = 'Recording produced no audio data. Please try again.';
                    return;
                }

                await this.saveToIndexedDB(this.recordedBlob);
                await this.uploadSingleFile(this.recordedBlob);
            }
        },

        // ── Upload Methods ─────────────────────────────
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async uploadChunk(blob, index) {
            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('chunk', blob, `chunk_${index}.${ext}`);
            formData.append('session_id', this.sessionId);
            formData.append('chunk_index', String(index));
            formData.append('mime_type', this.mimeType);

            try {
                const response = await fetch(this.chunkUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    this.uploadedChunks++;
                }
            } catch (err) {
                console.error('Chunk upload failed:', err);
                // Chunks will be retried on finalize or recovered from IndexedDB
            }
        },

        async finalizeChunkedUpload() {
            this.state = 'uploading';
            this.uploadProgress = 90; // Most data already uploaded during recording

            try {
                const response = await fetch(this.finalizeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        mime_type: this.mimeType,
                        duration_seconds: this.timer,
                        language: 'en',
                    }),
                });

                if (response.ok) {
                    this.uploadProgress = 100;
                    this.state = 'complete';
                    this.successMessage = 'Recording uploaded. Transcription in progress...';
                    const data = await response.json();
                    this.$dispatch('recording-complete', { transcription: data.transcription });
                } else {
                    throw new Error('Finalize failed');
                }
            } catch (err) {
                this.state = 'error';
                this.errorMessage = 'Failed to finalize recording. Please try again.';
            }
        },

        async uploadSingleFile(blob) {
            this.state = 'uploading';
            this.uploadProgress = 0;

            const formData = new FormData();
            const ext = this.mimeType.includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, `recording_${Date.now()}.${ext}`);
            formData.append('language', 'en');

            try {
                const xhr = new XMLHttpRequest();

                const uploadPromise = new Promise((resolve, reject) => {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            reject(new Error(`Upload failed: ${xhr.status}`));
                        }
                    };

                    xhr.onerror = () => reject(new Error('Network error'));
                });

                xhr.open('POST', this.uploadUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken());
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.send(formData);

                const data = await uploadPromise;
                this.uploadProgress = 100;
                this.state = 'complete';
                this.successMessage = 'Recording uploaded. Transcription in progress...';
                this.removeFromIndexedDB();
                this.$dispatch('recording-complete', { transcription: data.transcription });
            } catch (err) {
                await this.handleUploadError(err, blob);
            }
        },

        async handleUploadError(err, blob, retryCount = 0) {
            console.error('Upload error:', err);

            if (retryCount < 3) {
                const delays = [2000, 5000, 10000];
                this.errorMessage = `Upload failed. Retrying in ${delays[retryCount] / 1000}s...`;
                await new Promise(resolve => setTimeout(resolve, delays[retryCount]));
                this.errorMessage = '';

                return this.uploadSingleFile(blob, retryCount + 1);
            }

            this.state = 'error';
            this.errorMessage = 'Upload failed after multiple attempts. Your recording is saved locally — click Retry to try again.';
        },

        retryUpload() {
            if (this.recordedBlob) {
                this.uploadSingleFile(this.recordedBlob);
            } else if (this.isLongRecording) {
                this.finalizeChunkedUpload();
            } else {
                this.loadFromIndexedDB();
            }
        },

        // ── IndexedDB Recovery ─────────────────────────
        getDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('antaraflow-recordings', 1);
                request.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('recordings')) {
                        db.createObjectStore('recordings', { keyPath: 'id' });
                    }
                };
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        async saveToIndexedDB(blob) {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                tx.objectStore('recordings').put({
                    id: `${this.meetingId}-${this.sessionId}`,
                    meetingId: this.meetingId,
                    blob: blob,
                    mimeType: this.mimeType,
                    duration: this.timer,
                    timestamp: new Date().toISOString(),
                });
            } catch (err) {
                console.error('IndexedDB save failed:', err);
            }
        },

        async removeFromIndexedDB() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                tx.objectStore('recordings').delete(`${this.meetingId}-${this.sessionId}`);
            } catch (err) {
                console.error('IndexedDB delete failed:', err);
            }
        },

        async checkPendingRecovery() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readonly');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );

                    if (recordings.length > 0) {
                        this.hasPendingRecovery = true;
                        this.recoveryTimestamp = recordings[0].timestamp;
                    }
                };
            } catch {
                // IndexedDB not available
            }
        },

        async recoverRecording() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readonly');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );

                    if (recordings.length > 0) {
                        const recording = recordings[0];
                        this.recordedBlob = recording.blob;
                        this.mimeType = recording.mimeType;
                        this.timer = recording.duration;
                        this.sessionId = recording.id.split('-').slice(1).join('-');
                        this.hasPendingRecovery = false;
                        this.uploadSingleFile(this.recordedBlob);
                    }
                };
            } catch (err) {
                this.errorMessage = 'Could not recover recording.';
            }
        },

        async discardRecovery() {
            try {
                const db = await this.getDB();
                const tx = db.transaction('recordings', 'readwrite');
                const store = tx.objectStore('recordings');
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result.filter(
                        (r) => r.meetingId === this.meetingId
                    );
                    const deleteTx = db.transaction('recordings', 'readwrite');
                    const deleteStore = deleteTx.objectStore('recordings');
                    recordings.forEach((r) => deleteStore.delete(r.id));
                };

                this.hasPendingRecovery = false;
            } catch {
                this.hasPendingRecovery = false;
            }
        },

        // ── Audio Feedback ─────────────────────────────
        playBeep(frequency, duration) {
            try {
                const ctx = new AudioContext();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.connect(gain);
                gain.connect(ctx.destination);

                oscillator.frequency.value = frequency;
                gain.gain.value = 0.1;

                oscillator.start();
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
                oscillator.stop(ctx.currentTime + duration / 1000);
            } catch {
                // Audio feedback is optional
            }
        },

        // ── Reset & Cleanup ────────────────────────────
        resetRecorder() {
            this.state = this.mediaStream ? 'ready' : 'idle';
            this.timer = 0;
            this.chunks = [];
            this.recordedBlob = null;
            this.errorMessage = '';
            this.successMessage = '';
            this.uploadProgress = 0;
            this.chunkIndex = 0;
            this.uploadedChunks = 0;
            this.isLongRecording = false;

            if (this.mediaStream) {
                this.drawWaveform();
            }
        },

        cleanup() {
            clearInterval(this.timerInterval);
            clearInterval(this.countdownInterval);
            clearInterval(this.chunkInterval);

            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
            }

            if (this.audioContext?.state !== 'closed') {
                this.audioContext?.close();
            }

            if (this.mediaStream) {
                this.mediaStream.getTracks().forEach((track) => track.stop());
            }
        },
    };
}
```

**Step 2: Register in app.js**

Modify `resources/js/app.js`:

```javascript
import './bootstrap';
import Alpine from 'alpinejs';
import appState from './navigation';
import audioRecorder from './audio-recorder';

window.Alpine = Alpine;
Alpine.data('appState', appState);
Alpine.data('audioRecorder', audioRecorder);
Alpine.start();
```

**Step 3: Build and verify no JS errors**

Run: `npm run build`
Expected: Build succeeds with no errors

**Step 4: Commit**

```bash
git add resources/js/audio-recorder.js resources/js/app.js
git commit -m "feat: add Alpine.js audio recorder component with state machine"
```

---

## Task 6: Create the Blade recording UI

**Files:**
- Modify: `resources/views/meetings/wizard/step-inputs.blade.php` (replace "Coming Soon" placeholder at lines 432-444)

**Step 1: Replace the placeholder**

Replace the `{{-- Browser Recording Placeholder --}}` block (lines 432-444) with the full recording UI:

```blade
{{-- Browser Recording --}}
<div
    x-data="audioRecorder({
        uploadUrl: '{{ route('meetings.transcriptions.store', $meeting) }}',
        chunkUrl: '{{ route('meetings.audio-chunks.store', $meeting) }}',
        finalizeUrl: '{{ route('meetings.audio-chunks.finalize', $meeting) }}',
        cancelUrl: '{{ route('meetings.audio-chunks.destroy', $meeting) }}',
        meetingId: {{ $meeting->id }},
    })"
    class="mt-4 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden"
>
    {{-- Recovery Banner --}}
    <template x-if="hasPendingRecovery">
        <div class="bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800 p-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <span class="text-xs text-amber-700 dark:text-amber-300">
                    Unsaved recording found from <span x-text="new Date(recoveryTimestamp).toLocaleString()"></span>
                </span>
            </div>
            <div class="flex gap-2">
                <button @click="recoverRecording()" class="text-xs font-medium text-amber-700 dark:text-amber-300 hover:underline">Recover</button>
                <button @click="discardRecovery()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">Discard</button>
            </div>
        </div>
    </template>

    <div class="p-4">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="h-6 w-6 rounded-full flex items-center justify-center"
                     :class="{
                         'bg-gray-100 dark:bg-gray-700': state === 'idle',
                         'bg-green-100 dark:bg-green-900/30': state === 'ready',
                         'bg-red-100 dark:bg-red-900/30': ['recording', 'countdown'].includes(state),
                         'bg-amber-100 dark:bg-amber-900/30': state === 'paused',
                         'bg-blue-100 dark:bg-blue-900/30': isProcessing,
                         'bg-green-100 dark:bg-green-900/30': state === 'complete',
                     }">
                    {{-- Pulsing red dot during recording --}}
                    <div x-show="state === 'recording'"
                         class="h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse"></div>
                    {{-- Static dots for other states --}}
                    <div x-show="state !== 'recording'"
                         class="h-2.5 w-2.5 rounded-full"
                         :class="{
                             'bg-gray-400': state === 'idle',
                             'bg-green-500': state === 'ready',
                             'bg-red-500': state === 'countdown',
                             'bg-amber-500': state === 'paused',
                             'bg-blue-500': isProcessing,
                             'bg-green-500': state === 'complete',
                         }"></div>
                </div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">Browser Recording</p>
            </div>

            {{-- Timer --}}
            <div x-show="['recording', 'paused', 'stopping'].includes(state)"
                 class="flex items-center gap-1.5 text-sm font-mono"
                 :class="state === 'paused' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'">
                <div x-show="state === 'recording'" class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></div>
                <span x-text="formattedTimer"></span>
                <span x-show="state === 'paused'" class="text-xs font-sans">(Paused)</span>
            </div>
        </div>

        {{-- Waveform Canvas --}}
        <div x-show="!['idle', 'complete'].includes(state) || state === 'ready'"
             class="relative mb-3 rounded-lg overflow-hidden bg-gray-50 dark:bg-gray-700/50"
             style="height: 64px;">

            <canvas x-ref="waveformCanvas"
                    width="600"
                    height="64"
                    class="w-full h-full"></canvas>

            {{-- Countdown Overlay --}}
            <div x-show="state === 'countdown'"
                 x-transition
                 class="absolute inset-0 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                <span x-text="countdownValue"
                      class="text-3xl font-bold text-white"></span>
            </div>
        </div>

        {{-- Controls --}}
        <div class="flex items-center gap-2">
            {{-- Start Recording (idle / ready) --}}
            <template x-if="['idle', 'ready'].includes(state)">
                <button @click="startRecording()"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 transition-colors">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="6" />
                    </svg>
                    <span x-text="state === 'ready' ? 'Record' : 'Start Recording'"></span>
                </button>
            </template>

            {{-- Pause / Resume (recording / paused) --}}
            <template x-if="state === 'recording'">
                <div class="flex items-center gap-2">
                    <button @click="pauseRecording()"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="4" width="4" height="16" />
                            <rect x="14" y="4" width="4" height="16" />
                        </svg>
                        Pause
                    </button>
                    <button @click="stopRecording()"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-gray-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="1" />
                        </svg>
                        Stop
                    </button>
                </div>
            </template>

            <template x-if="state === 'paused'">
                <div class="flex items-center gap-2">
                    <button @click="resumeRecording()"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 transition-colors">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <polygon points="5,3 19,12 5,21" />
                        </svg>
                        Resume
                    </button>
                    <button @click="stopRecording()"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-gray-800 dark:bg-gray-600 hover:bg-gray-900 dark:hover:bg-gray-500 transition-colors">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="1" />
                        </svg>
                        Stop
                    </button>
                </div>
            </template>

            {{-- Processing States --}}
            <template x-if="state === 'stopping'">
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Finalizing audio...
                </div>
            </template>

            <template x-if="state === 'processing'">
                <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Processing audio...
                </div>
            </template>

            {{-- Upload Progress --}}
            <template x-if="state === 'uploading'">
                <div class="flex-1">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span>Uploading...</span>
                        <span x-text="uploadProgress + '%'"></span>
                    </div>
                    <div class="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-500 transition-all duration-300"
                             :style="{ width: uploadProgress + '%' }"></div>
                    </div>
                </div>
            </template>

            {{-- Complete --}}
            <template x-if="state === 'complete'">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="successMessage"></span>
                    </div>
                    <button @click="resetRecorder()"
                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        Record another
                    </button>
                </div>
            </template>

            {{-- Error --}}
            <template x-if="state === 'error'">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="errorMessage" class="text-xs"></span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="retryUpload()"
                                x-show="recordedBlob || isLongRecording"
                                class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                            Retry
                        </button>
                        <button @click="resetRecorder()"
                                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            Reset
                        </button>
                    </div>
                </div>
            </template>

            {{-- Requesting Permission --}}
            <template x-if="state === 'requesting_permission'">
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-4 w-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    Please allow microphone access...
                </div>
            </template>
        </div>

        {{-- Long recording indicator --}}
        <div x-show="isLongRecording && state === 'recording'"
             class="mt-2 text-xs text-gray-400 dark:text-gray-500">
            Progressive upload active — <span x-text="uploadedChunks"></span> chunks uploaded
        </div>
    </div>
</div>
```

**Step 2: Add the `recording-complete` event listener to parent component**

In the parent `x-data` object at the top of `step-inputs.blade.php`, add an event listener. Find the existing `x-data` div and add the `@recording-complete` handler:

Add `@recording-complete.window="handleRecordingComplete($event.detail)"` to the parent div, and add the handler method to the x-data object:

```javascript
handleRecordingComplete(detail) {
    if (detail?.transcription) {
        this.transcriptions.push(detail.transcription);
        this.successMessage = 'Browser recording uploaded. Transcription processing...';
        setTimeout(() => this.successMessage = '', 4000);
    }
},
```

**Step 3: Build frontend**

Run: `npm run build`
Expected: Build succeeds

**Step 4: Commit**

```bash
git add resources/views/meetings/wizard/step-inputs.blade.php
git commit -m "feat: replace browser recording placeholder with full recording UI"
```

---

## Task 7: End-to-end manual verification & edge case tests

**Files:**
- Create: `tests/Feature/Transcription/BrowserRecordingIntegrationTest.php`

**Step 1: Write integration tests**

```php
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\InputType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->user = User::factory()->create();
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->user->organization_id,
    ]);
    $this->actingAs($this->user);
});

it('handles short recording upload via existing transcription endpoint with json', function () {
    $file = UploadedFile::fake()->create('recording.webm', 500, 'audio/webm');

    $response = $this->postJson(
        route('meetings.transcriptions.store', $this->meeting),
        ['audio' => $file, 'language' => 'en']
    );

    $response->assertOk();
    $response->assertJsonStructure(['message', 'transcription']);

    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);
});

it('handles full chunked recording lifecycle', function () {
    $sessionId = fake()->uuid();

    // Upload 3 chunks
    for ($i = 0; $i < 3; $i++) {
        $chunk = UploadedFile::fake()->create("chunk_{$i}.webm", 100, 'audio/webm');

        $response = $this->postJson(
            route('meetings.audio-chunks.store', $this->meeting),
            [
                'chunk' => $chunk,
                'session_id' => $sessionId,
                'chunk_index' => $i,
                'mime_type' => 'audio/webm',
            ]
        );

        $response->assertOk();
    }

    // Finalize
    $response = $this->postJson(
        route('meetings.audio-chunks.finalize', $this->meeting),
        [
            'session_id' => $sessionId,
            'mime_type' => 'audio/webm',
            'duration_seconds' => 420,
            'language' => 'en',
        ]
    );

    $response->assertOk();

    // Verify transcription created with BrowserRecording input type
    $transcription = AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription)->not->toBeNull();
    expect($transcription->duration_seconds)->toBe(420);

    $input = $this->meeting->inputs()
        ->where('source_type', AudioTranscription::class)
        ->where('source_id', $transcription->id)
        ->first();

    expect($input->type)->toBe(InputType::BrowserRecording);
});

it('cleans up chunks on cancel', function () {
    $sessionId = fake()->uuid();
    $chunk = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $this->postJson(
        route('meetings.audio-chunks.store', $this->meeting),
        [
            'chunk' => $chunk,
            'session_id' => $sessionId,
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ]
    );

    $response = $this->deleteJson(
        route('meetings.audio-chunks.destroy', $this->meeting),
        ['session_id' => $sessionId]
    );

    $response->assertOk();

    $chunkDir = "organizations/{$this->meeting->organization_id}/audio/chunks/{$sessionId}";
    expect(Storage::disk('local')->files($chunkDir))->toBeEmpty();
});
```

**Step 2: Run all tests**

Run: `php artisan test --compact`
Expected: ALL tests pass

**Step 3: Run Pint on all modified files**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Commit**

```bash
git add tests/Feature/Transcription/BrowserRecordingIntegrationTest.php
git commit -m "test: add integration tests for browser recording lifecycle"
```

---

## Task 8: Build and final verification

**Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: ALL tests pass

**Step 2: Build frontend assets**

Run: `npm run build`
Expected: Build succeeds with no errors

**Step 3: Run Pint on everything**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Final commit if any changes**

```bash
git add -A
git commit -m "chore: final cleanup for browser recording feature"
```

---

## Summary

| Task | What | Files |
|------|------|-------|
| 1 | Chunk storage methods | `AudioStorageService` + test |
| 2 | Chunk controller + routes | `AudioChunkController`, `StoreAudioChunkRequest`, routes, `TranscriptionService` + test |
| 3 | JSON response for short uploads | `TranscriptionController` + test |
| 4 | Stale chunk cleanup command | `CleanupStaleChunksCommand`, console routes + test |
| 5 | Alpine.js recorder component | `audio-recorder.js`, `app.js` |
| 6 | Blade recording UI | `step-inputs.blade.php` |
| 7 | Integration tests | `BrowserRecordingIntegrationTest` |
| 8 | Build & final verification | All |

**Total estimated commits:** 8
