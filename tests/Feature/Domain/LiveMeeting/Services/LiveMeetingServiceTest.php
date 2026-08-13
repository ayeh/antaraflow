<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\LiveMeeting\Notifications\LiveTranscriptIncompleteNotification;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\DiarizeTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    $this->service = app(LiveMeetingService::class);
});

test('starts a session successfully', function () {
    $session = $this->service->startSession($this->meeting, $this->user);

    expect($session)->toBeInstanceOf(LiveMeetingSession::class)
        ->and($session->status)->toBe(LiveSessionStatus::Active)
        ->and($session->minutes_of_meeting_id)->toBe($this->meeting->id)
        ->and($session->started_by)->toBe($this->user->id)
        ->and($session->started_at)->not->toBeNull()
        ->and($session->config)->toBe(['chunk_interval' => 30, 'extraction_interval' => 300, 'live_extraction' => false]);
});

test('starts a session with custom config', function () {
    $config = ['chunk_interval' => 15, 'extraction_interval' => 120];

    $session = $this->service->startSession($this->meeting, $this->user, $config);

    expect($session->config)->toBe([...$config, 'live_extraction' => false]);
});

test('throws exception when session already active', function () {
    LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->service->startSession($this->meeting, $this->user);
})->throws(RuntimeException::class);

test('ends a session and calculates duration', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(30),
        'status' => LiveSessionStatus::Active,
    ]);

    $this->service->endSession($session);

    $session->refresh();

    expect($session->status)->toBe(LiveSessionStatus::Ended)
        ->and($session->ended_at)->not->toBeNull()
        ->and($session->total_duration_seconds)->toBeGreaterThan(0);

    Queue::assertPushed(ExtractMeetingDataJob::class, function ($job) {
        return $job->meeting->id === $this->meeting->id;
    });
});

test('pauses a session', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->service->pauseSession($session);

    $session->refresh();

    expect($session->status)->toBe(LiveSessionStatus::Paused)
        ->and($session->paused_at)->not->toBeNull();
});

test('resumes a session', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'status' => LiveSessionStatus::Paused,
        'paused_at' => now(),
    ]);

    $this->service->resumeSession($session);

    $session->refresh();

    expect($session->status)->toBe(LiveSessionStatus::Active)
        ->and($session->paused_at)->toBeNull();
});

test('processes a chunk and stores file', function () {
    Storage::fake('local');
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $file = UploadedFile::fake()->create('chunk.webm', 512, 'audio/webm');

    $chunk = $this->service->processChunk($session, $file, 1, 0.0, 30.0);

    expect($chunk)->toBeInstanceOf(LiveTranscriptChunk::class)
        ->and($chunk->live_meeting_session_id)->toBe($session->id)
        ->and($chunk->chunk_number)->toBe(1)
        ->and($chunk->start_time)->toBe(0.0)
        ->and($chunk->end_time)->toBe(30.0)
        ->and($chunk->status)->toBe(ChunkStatus::Pending)
        ->and($chunk->audio_file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($chunk->audio_file_path);

    Queue::assertPushed(LiveTranscriptionJob::class, function ($job) use ($chunk) {
        return $job->chunk->id === $chunk->id;
    });
});

test('returns session state with chunks and extractions', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'First chunk text.',
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'text' => 'Second chunk text.',
    ]);

    $state = $this->service->getSessionState($session);

    expect($state)->toHaveKeys(['session', 'chunks', 'extractions'])
        ->and($state['chunks'])->toHaveCount(2)
        ->and($state['session']->id)->toBe($session->id);
});

test('merges chunks into final audio transcription on session end', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(30),
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Hello everyone.',
        'speaker' => 'Speaker A',
        'start_time' => 0.0,
        'end_time' => 15.0,
        'confidence' => 0.95,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'text' => 'Let us begin the meeting.',
        'speaker' => 'Speaker B',
        'start_time' => 15.0,
        'end_time' => 30.0,
        'confidence' => 0.92,
    ]);

    $this->service->endSession($session);

    $transcription = AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription)->not->toBeNull()
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toContain('Hello everyone.')
        ->and($transcription->full_text)->toContain('Let us begin the meeting.');

    $segments = TranscriptionSegment::query()
        ->where('audio_transcription_id', $transcription->id)
        ->orderBy('sequence_order')
        ->get();

    expect($segments)->toHaveCount(2)
        ->and($segments->first()->text)->toBe('Hello everyone.')
        ->and($segments->first()->speaker)->toBe('Speaker A')
        ->and($segments->last()->text)->toBe('Let us begin the meeting.');

    $this->assertDatabaseHas('mom_inputs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'source_type' => AudioTranscription::class,
        'source_id' => $transcription->id,
    ]);
});

test('end session without completed chunks does not create transcription', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(5),
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'status' => ChunkStatus::Pending,
    ]);

    $this->service->endSession($session);

    $transcription = AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription)->toBeNull();

    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Ended);
});

test('records dropped chunks on the transcription when the merge is incomplete', function () {
    Queue::fake();
    Log::spy();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(30),
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Hello everyone.',
        'start_time' => 0.0,
        'end_time' => 15.0,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'status' => ChunkStatus::Failed,
        'text' => null,
        'start_time' => 15.0,
        'end_time' => 30.0,
    ]);

    $this->service->endSession($session);

    $transcription = AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription->provider_metadata)->toBe([
        'merged_chunks' => 1,
        'dropped_chunks' => 1,
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'incomplete')
            && $context['dropped_chunks'] === 1);
});

test('notifies the organiser once when the live transcript is incomplete', function () {
    Queue::fake();
    Notification::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(30),
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Hello everyone.',
        'start_time' => 0.0,
        'end_time' => 30.0,
    ]);

    // Three failed chunks spanning 90 seconds of the meeting.
    foreach ([2, 3, 4] as $i) {
        LiveTranscriptChunk::factory()->create([
            'live_meeting_session_id' => $session->id,
            'chunk_number' => $i,
            'status' => ChunkStatus::Failed,
            'text' => null,
            'start_time' => ($i - 1) * 30.0,
            'end_time' => $i * 30.0,
        ]);
    }

    $this->service->endSession($session);

    Notification::assertSentTo(
        $this->user,
        LiveTranscriptIncompleteNotification::class,
        function (LiveTranscriptIncompleteNotification $notification) {
            $payload = $notification->toArray($this->user);

            return $payload['dropped_chunks'] === 3
                && $payload['merged_chunks'] === 1
                && $payload['missing_minutes'] === 2;
        },
    );

    // One notice for the whole session, not one per failed chunk.
    Notification::assertSentToTimes($this->user, LiveTranscriptIncompleteNotification::class, 1);
});

test('sends no incomplete notice when every chunk transcribed', function () {
    Queue::fake();
    Notification::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'started_at' => now()->subMinutes(30),
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'All good.',
        'start_time' => 0.0,
        'end_time' => 30.0,
    ]);

    $this->service->endSession($session);

    Notification::assertNothingSent();
});

test('lands each chunk segment at its true position on the meeting timeline', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'text' => 'good morning everyone',
        'start_time' => 0.0,
        'end_time' => 15.0,
        'segments' => [
            ['text' => 'good morning', 'start_time' => 1.0, 'end_time' => 2.5, 'speaker' => null, 'confidence' => 0.9],
            ['text' => 'everyone', 'start_time' => 2.5, 'end_time' => 4.0, 'speaker' => null, 'confidence' => 0.9],
        ],
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 3,
        'text' => 'we agree',
        'start_time' => 45.0,
        'end_time' => 60.0,
        'segments' => [
            ['text' => 'we agree', 'start_time' => 2.0, 'end_time' => 4.0, 'speaker' => null, 'confidence' => 0.8],
        ],
    ]);

    $this->service->endSession($session);

    $segments = TranscriptionSegment::query()
        ->where('audio_transcription_id', AudioTranscription::query()->latest('id')->first()->id)
        ->orderBy('sequence_order')
        ->get();

    expect($segments)->toHaveCount(3)
        ->and($segments->pluck('text')->all())->toBe(['good morning', 'everyone', 'we agree'])
        // The third segment sits two seconds into a chunk that starts at 45.
        ->and($segments->pluck('start_time')->all())->toBe([1.0, 2.5, 47.0])
        ->and($segments->pluck('sequence_order')->all())->toBe([0, 1, 2]);
});

// Every sitting recorded before chunks carried their own segments, and
// everything the browser recorder sends. These must keep behaving exactly as
// they do today: one coarse segment spanning the whole chunk.
test('falls back to one segment per chunk when a chunk carried none', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'text' => 'minutes of the meeting',
        'speaker' => 'Speaker A',
        'start_time' => 0.0,
        'end_time' => 15.0,
        'confidence' => 0.91,
        'segments' => null,
    ]);

    $this->service->endSession($session);

    $segments = TranscriptionSegment::query()
        ->where('audio_transcription_id', AudioTranscription::query()->latest('id')->first()->id)
        ->get();

    expect($segments)->toHaveCount(1)
        ->and($segments->first()->text)->toBe('minutes of the meeting')
        ->and($segments->first()->speaker)->toBe('Speaker A')
        ->and($segments->first()->start_time)->toBe(0.0)
        ->and($segments->first()->end_time)->toBe(15.0);
});

test('mixes fine and coarse chunks in one sitting without losing the order', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'text' => 'opening remarks',
        'start_time' => 0.0,
        'end_time' => 15.0,
        'segments' => null,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'first item second item',
        'start_time' => 15.0,
        'end_time' => 30.0,
        'segments' => [
            ['text' => 'first item', 'start_time' => 0.0, 'end_time' => 5.0, 'speaker' => null, 'confidence' => 0.9],
            ['text' => 'second item', 'start_time' => 5.0, 'end_time' => 10.0, 'speaker' => null, 'confidence' => 0.9],
        ],
    ]);

    $this->service->endSession($session);

    $segments = TranscriptionSegment::query()
        ->where('audio_transcription_id', AudioTranscription::query()->latest('id')->first()->id)
        ->orderBy('sequence_order')
        ->get();

    expect($segments->pluck('text')->all())->toBe(['opening remarks', 'first item', 'second item'])
        ->and($segments->pluck('start_time')->all())->toBe([0.0, 15.0, 20.0]);
});

test('asks for the speakers to be named once the sitting is merged', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'text' => 'the chair opened the meeting',
    ]);

    $this->service->endSession($session);

    Queue::assertPushed(DiarizeTranscriptionJob::class);
    Queue::assertPushed(ExtractMeetingDataJob::class);
});

// Naming the speakers in a transcript that does not exist would fail on a
// null, in a job nobody is watching, for every sitting that recorded nothing.
test('does not ask for names when no chunk ever transcribed', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->service->endSession($session);

    Queue::assertNotPushed(DiarizeTranscriptionJob::class);
});
