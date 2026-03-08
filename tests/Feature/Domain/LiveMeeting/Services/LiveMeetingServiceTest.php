<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        ->and($session->config)->toBe(['chunk_interval' => 30, 'extraction_interval' => 300]);
});

test('starts a session with custom config', function () {
    $config = ['chunk_interval' => 15, 'extraction_interval' => 120];

    $session = $this->service->startSession($this->meeting, $this->user, $config);

    expect($session->config)->toBe($config);
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
