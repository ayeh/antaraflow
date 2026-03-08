<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('full live meeting flow: start session, upload chunks, end session, verify merge', function () {
    Queue::fake();
    Storage::fake('local');

    // Step 1: Start a live session via controller endpoint.
    $startResponse = $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting));

    $startResponse->assertCreated();
    $startResponse->assertJsonStructure(['session' => ['id', 'status', 'started_at']]);

    $sessionId = $startResponse->json('session.id');

    $this->assertDatabaseHas('live_meeting_sessions', [
        'id' => $sessionId,
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active->value,
    ]);

    // Step 2: Upload audio chunks via controller endpoint.
    $session = LiveMeetingSession::find($sessionId);

    $chunk1File = UploadedFile::fake()->create('chunk_0.webm', 100, 'audio/webm');
    $chunk1Response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $chunk1File,
            'chunk_number' => 0,
            'start_time' => 0.0,
            'end_time' => 30.0,
        ]);

    $chunk1Response->assertCreated();
    $chunk1Response->assertJsonStructure(['chunk' => ['id', 'chunk_number', 'status']]);

    $chunk2File = UploadedFile::fake()->create('chunk_1.webm', 120, 'audio/webm');
    $chunk2Response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $chunk2File,
            'chunk_number' => 1,
            'start_time' => 30.0,
            'end_time' => 60.0,
        ]);

    $chunk2Response->assertCreated();

    // Step 3: Verify LiveTranscriptionJob was dispatched for each chunk.
    Queue::assertPushed(LiveTranscriptionJob::class, 2);

    $this->assertDatabaseCount('live_transcript_chunks', 2);
    $this->assertDatabaseHas('live_transcript_chunks', [
        'live_meeting_session_id' => $sessionId,
        'chunk_number' => 0,
    ]);
    $this->assertDatabaseHas('live_transcript_chunks', [
        'live_meeting_session_id' => $sessionId,
        'chunk_number' => 1,
    ]);

    // Step 4: Simulate completed chunks (as if transcription jobs ran).
    LiveTranscriptChunk::query()
        ->where('live_meeting_session_id', $sessionId)
        ->where('chunk_number', 0)
        ->update([
            'status' => ChunkStatus::Completed,
            'text' => 'Welcome everyone to the meeting.',
            'speaker' => 'Speaker A',
            'confidence' => 0.95,
        ]);

    LiveTranscriptChunk::query()
        ->where('live_meeting_session_id', $sessionId)
        ->where('chunk_number', 1)
        ->update([
            'status' => ChunkStatus::Completed,
            'text' => 'Let us review the agenda items.',
            'speaker' => 'Speaker B',
            'confidence' => 0.92,
        ]);

    // Step 5: Verify session state returns completed chunks.
    $stateResponse = $this->actingAs($this->user)
        ->getJson(route('meetings.live.state', [$this->meeting, $session]));

    $stateResponse->assertOk();
    $stateResponse->assertJsonStructure(['session', 'chunks', 'extractions']);
    expect($stateResponse->json('chunks'))->toHaveCount(2);

    // Step 6: End session. Since Queue::fake() is active, we need to stop faking
    // so the service's endSession method can dispatch ExtractMeetingDataJob and we
    // can verify it. Use Bus::fake() for targeted assertion instead.
    Queue::swap(app('queue'));
    Bus::fake([ExtractMeetingDataJob::class]);

    $endResponse = $this->actingAs($this->user)
        ->postJson(route('meetings.live.end', [$this->meeting, $session]));

    $endResponse->assertOk();
    $endResponse->assertJson(['message' => 'Session ended successfully.']);

    // Step 7: Verify session is marked as ended.
    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Ended);
    expect($session->ended_at)->not->toBeNull();
    expect($session->total_duration_seconds)->toBeGreaterThanOrEqual(0);

    // Step 8: Verify chunks were merged into AudioTranscription.
    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    // Verify the full text combines both chunks.
    $transcription = \App\Domain\Transcription\Models\AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription)->not->toBeNull();
    expect($transcription->full_text)->toContain('Welcome everyone to the meeting.');
    expect($transcription->full_text)->toContain('Let us review the agenda items.');

    // Verify TranscriptionSegments were created.
    $this->assertDatabaseCount('transcription_segments', 2);

    // Verify MomInput was created.
    $this->assertDatabaseHas('mom_inputs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'source_type' => \App\Domain\Transcription\Models\AudioTranscription::class,
        'source_id' => $transcription->id,
    ]);

    // Step 9: Verify ExtractMeetingDataJob was dispatched.
    Bus::assertDispatched(ExtractMeetingDataJob::class, function ($job) {
        return $job->meeting->id === $this->meeting->id;
    });
});

test('chunk upload dispatches LiveTranscriptionJob on correct queue', function () {
    Queue::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $audioFile = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $audioFile,
            'chunk_number' => 0,
            'start_time' => 0.0,
            'end_time' => 30.0,
        ])
        ->assertCreated();

    Queue::assertPushedOn('live-transcription', LiveTranscriptionJob::class);
});

test('TranscriptionChunkProcessed event is fired when chunk completes', function () {
    Event::fake([TranscriptionChunkProcessed::class]);
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'status' => ChunkStatus::Pending,
        'audio_file_path' => 'test/chunk.webm',
        'text' => null,
        'speaker' => null,
        'confidence' => null,
    ]);

    // Create a dummy audio file for the transcriber to read.
    Storage::disk('local')->put('test/chunk.webm', 'fake-audio-data');

    // Mock the TranscriberInterface so we don't need a real transcription service.
    $transcriptionResult = new \App\Infrastructure\AI\DTOs\TranscriptionResult(
        fullText: 'Transcribed text from audio chunk.',
        confidence: 0.95,
        segments: [
            new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
                text: 'Transcribed text from audio chunk.',
                startTime: 0.0,
                endTime: 30.0,
                speaker: 'Speaker A',
                confidence: 0.95,
            ),
        ],
    );

    $mockTranscriber = Mockery::mock(\App\Infrastructure\AI\Contracts\TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn($transcriptionResult);

    $this->app->instance(\App\Infrastructure\AI\Contracts\TranscriberInterface::class, $mockTranscriber);

    // Run the job directly.
    (new LiveTranscriptionJob($chunk))->handle($mockTranscriber);

    // Verify chunk was updated.
    $chunk->refresh();
    expect($chunk->status)->toBe(ChunkStatus::Completed);
    expect($chunk->text)->toBe('Transcribed text from audio chunk.');
    expect($chunk->speaker)->toBe('Speaker A');

    // Verify event was fired.
    Event::assertDispatched(TranscriptionChunkProcessed::class, function ($event) use ($chunk) {
        return $event->chunk->id === $chunk->id;
    });
});

test('ending session without completed chunks does not create transcription', function () {
    Bus::fake([ExtractMeetingDataJob::class]);

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    // Create a pending (not completed) chunk.
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.end', [$this->meeting, $session]))
        ->assertOk();

    // No transcription should be created since no chunks are completed.
    $this->assertDatabaseCount('audio_transcriptions', 0);
    $this->assertDatabaseCount('transcription_segments', 0);

    // ExtractMeetingDataJob should still be dispatched.
    Bus::assertDispatched(ExtractMeetingDataJob::class);
});

test('cannot start a second live session while one is active', function () {
    // Start first session.
    $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting))
        ->assertCreated();

    // Attempt to start a second session.
    $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting))
        ->assertConflict();

    $this->assertDatabaseCount('live_meeting_sessions', 1);
});
