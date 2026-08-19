<?php

declare(strict_types=1);

use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Models\OrganizationAiBudget;
use App\Domain\AI\Services\AiCircuitBreaker;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Transcription\Services\AudioConditioner;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('transcribes chunk and updates status to completed', function () {
    Event::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
        'speaker' => null,
        'confidence' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturn(new TranscriptionResult(
            fullText: 'This is the transcribed text',
            confidence: 0.92,
            segments: [
                new TranscriptionSegmentData(
                    text: 'This is the transcribed text',
                    startTime: 0.0,
                    endTime: 5.0,
                    speaker: 'Speaker A',
                    confidence: 0.92,
                ),
            ],
        ));

    $job = new LiveTranscriptionJob($chunk);
    $job->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Completed)
        ->and($chunk->text)->toBe('This is the transcribed text')
        ->and($chunk->speaker)->toBe('Speaker A')
        ->and($chunk->confidence)->toBe(0.92);
});

test('broadcasts TranscriptionChunkProcessed event on success', function () {
    Event::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturn(new TranscriptionResult(
            fullText: 'Test transcription',
            confidence: 0.9,
            segments: [
                new TranscriptionSegmentData(
                    text: 'Test transcription',
                    startTime: 0.0,
                    endTime: 3.0,
                    speaker: 'Speaker B',
                    confidence: 0.9,
                ),
            ],
        ));

    $job = new LiveTranscriptionJob($chunk);
    $job->handle(fakeTranscriberFactory($mockTranscriber));

    Event::assertDispatched(TranscriptionChunkProcessed::class, function ($event) use ($chunk) {
        return $event->chunk->id === $chunk->id;
    });
});

test('updates chunk to failed status on failure', function () {
    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'status' => ChunkStatus::Processing,
    ]);

    $job = new LiveTranscriptionJob($chunk);
    $job->failed(new RuntimeException('Transcription service unavailable'));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Failed)
        ->and($chunk->error_message)->toBe('Transcription service unavailable');
});

test('sets processing status before transcribing', function () {
    Event::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $statusDuringTranscription = null;
    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function () use ($chunk, &$statusDuringTranscription) {
            $chunk->refresh();
            $statusDuringTranscription = $chunk->status;

            return new TranscriptionResult(
                fullText: 'Test',
                confidence: 0.9,
                segments: [],
            );
        });

    $job = new LiveTranscriptionJob($chunk);
    $job->handle(fakeTranscriberFactory($mockTranscriber));

    expect($statusDuringTranscription)->toBe(ChunkStatus::Processing);
});

test('passes correct language option from meeting', function () {
    Event::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create();
    $session->meeting->update(['language' => 'fr']);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->withArgs(function (string $filePath, array $options) {
            return $options['language'] === 'fr';
        })
        ->andReturn(new TranscriptionResult(
            fullText: 'Bonjour',
            confidence: 0.88,
            segments: [
                new TranscriptionSegmentData(
                    text: 'Bonjour',
                    startTime: 0.0,
                    endTime: 1.0,
                    speaker: 'Orateur',
                    confidence: 0.88,
                ),
            ],
        ));

    $job = new LiveTranscriptionJob($chunk);
    $job->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();
    expect($chunk->text)->toBe('Bonjour');
});

test('trips the circuit breaker and abandons the chunk on a provider quota failure', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andThrow(AiQuotaExceededException::make('You exceeded your current quota'));

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Failed)
        ->and($chunk->error_message)->toContain('exceeded your current quota')
        ->and(app(AiCircuitBreaker::class)->isOpen(LiveTranscriptionJob::CIRCUIT))->toBeTrue();
});

test('skips the provider entirely while the circuit breaker is open', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->trip(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldNotReceive('transcribe');

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Failed)
        ->and($chunk->error_message)->toContain('paused');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);
});

test('blocks the chunk when the organization is over its AI budget', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();

    OrganizationAiBudget::query()->create([
        'organization_id' => $session->meeting->organization_id,
        'daily_limit' => 0.01,
        'monthly_limit' => null,
    ]);

    AiUsageLog::query()->create([
        'organization_id' => $session->meeting->organization_id,
        'provider' => 'openai',
        'model' => 'whisper-1',
        'feature' => 'live_transcription',
        'cost' => 5.00,
        'status' => 'success',
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldNotReceive('transcribe');

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Failed)
        ->and($chunk->error_message)->toContain('budget');
});

test('sends attendee names, both languages and the previous chunk as context', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();
    $session->meeting->update(['language' => 'ms', 'title' => 'CR ePengambilan']);
    $session->meeting->attendees()->create(['name' => 'Kak Nisa', 'company' => 'Jabatan Perikanan Malaysia']);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Okay so kita pergi ke portal dulu',
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $captured = [];

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function (string $path, array $options) use (&$captured) {
            $captured = $options;

            return new TranscriptionResult(fullText: 'ok', confidence: null, segments: []);
        });

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($captured['keywords'])->toContain('Kak Nisa', 'Jabatan Perikanan Malaysia', 'CR ePengambilan')
        ->and($captured['languages'])->toContain('ms', 'en')
        ->and($captured['prompt'])->toContain('kita pergi ke portal dulu');
});

test('sends no context for the first chunk of a session', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'audio_file_path' => 'live-chunks/test-audio.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/test-audio.webm', 'fake-audio-data');

    $captured = ['prompt' => 'unset'];

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function (string $path, array $options) use (&$captured) {
            $captured = $options;

            return new TranscriptionResult(fullText: 'ok', confidence: null, segments: []);
        });

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($captured['prompt'])->toBeNull();
});

// The upload path has always run audio through a highpass and EBU R128
// normalisation before sending it. Live never did, so a phone on a boardroom
// table sent the room exactly as it heard it — a hall recording measured -29
// dBFS mean and came back with a quarter of the words a close recording gave.
test('conditions the chunk before sending it to the transcriber', function () {
    Event::fake();
    Storage::fake('local');
    Process::fake();

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/quiet.webm',
        'status' => ChunkStatus::Pending,
    ]);

    Storage::disk('local')->put('live-chunks/quiet.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn(
        new TranscriptionResult(fullText: 'ok', confidence: null, segments: []),
    );

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    Process::assertRan(function ($process) {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, AudioConditioner::FILTER_CHAIN);
    });
});

// ffmpeg being absent or wedged must cost the sitting a little quality, not
// the chunk. The job still has to transcribe something.
test('still transcribes when conditioning fails', function () {
    Event::fake();
    Storage::fake('local');
    Process::fake(['*' => Process::result(output: '', errorOutput: 'not found', exitCode: 127)]);

    $session = LiveMeetingSession::factory()->create();
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'audio_file_path' => 'live-chunks/quiet.webm',
        'status' => ChunkStatus::Pending,
    ]);

    Storage::disk('local')->put('live-chunks/quiet.webm', 'fake-audio-data');

    $sentPath = null;
    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()
        ->andReturnUsing(function (string $path) use (&$sentPath) {
            $sentPath = $path;

            return new TranscriptionResult(fullText: 'heard it anyway', confidence: null, segments: []);
        });

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($sentPath)->toEndWith('quiet.webm')
        ->and($chunk->fresh()->status)->toBe(ChunkStatus::Completed)
        ->and($chunk->fresh()->text)->toBe('heard it anyway');
});

test('keeps every segment the transcriber returned, on the chunk own clock', function () {
    Event::fake();
    Storage::fake('local');

    $chunk = LiveTranscriptChunk::factory()->create([
        'audio_file_path' => 'live-chunks/three.webm',
        'status' => ChunkStatus::Pending,
        'start_time' => 45.0,
        'end_time' => 60.0,
    ]);

    Storage::disk('local')->put('live-chunks/three.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn(new TranscriptionResult(
        fullText: 'we agree on the budget',
        confidence: 0.9,
        // Every chunk is transcribed alone, so its segments always start near
        // zero regardless of where the chunk sits in the meeting.
        segments: [
            new TranscriptionSegmentData(text: 'we agree', startTime: 0.0, endTime: 2.5, confidence: 0.9),
            new TranscriptionSegmentData(text: 'on the budget', startTime: 2.5, endTime: 5.0, confidence: 0.88),
        ],
    ));

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $segments = $chunk->fresh()->segments;

    // Compared loosely on purpose. A whole number survives the JSON round trip
    // as an int — 0.0 goes in and 0 comes back — so anything reading these has
    // to cast rather than trust the type, and the merge does exactly that.
    expect($segments)->toHaveCount(2)
        ->and($segments[0]['text'])->toBe('we agree')
        ->and($segments[0]['start_time'])->toEqual(0.0)
        ->and($segments[1]['text'])->toBe('on the budget')
        ->and($segments[1]['end_time'])->toEqual(5.0);
});

test('replaces the segments on a retry rather than accumulating them', function () {
    Event::fake();
    Storage::fake('local');

    $chunk = LiveTranscriptChunk::factory()->create([
        'audio_file_path' => 'live-chunks/again.webm',
        'status' => ChunkStatus::Pending,
        'segments' => [
            ['text' => 'stale', 'start_time' => 0.0, 'end_time' => 1.0, 'speaker' => null, 'confidence' => null],
        ],
    ]);

    Storage::disk('local')->put('live-chunks/again.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn(new TranscriptionResult(
        fullText: 'fresh',
        confidence: 0.9,
        segments: [new TranscriptionSegmentData(text: 'fresh', startTime: 0.0, endTime: 1.0, confidence: 0.9)],
    ));

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $segments = $chunk->fresh()->segments;

    expect($segments)->toHaveCount(1)
        ->and($segments[0]['text'])->toBe('fresh');
});

// A provider that returns no segments must not be mistaken later for a chunk
// that was never transcribed at all.
test('records an empty segment list rather than null when there were none', function () {
    Event::fake();
    Storage::fake('local');

    $chunk = LiveTranscriptChunk::factory()->create([
        'audio_file_path' => 'live-chunks/none.webm',
        'status' => ChunkStatus::Pending,
    ]);

    Storage::disk('local')->put('live-chunks/none.webm', 'fake-audio-data');

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn(
        new TranscriptionResult(fullText: 'no segments here', confidence: null, segments: []),
    );

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($chunk->fresh()->segments)->toBe([]);
});

// Silence or room noise comes back as one word looped for the whole chunk.
// Storing that fills the transcript with garbage, so it is dropped to the same
// empty state as a silent chunk rather than kept.
test('drops a looped hallucination instead of storing it', function () {
    Event::fake();
    Storage::fake('local');

    $chunk = LiveTranscriptChunk::factory()->create([
        'audio_file_path' => 'live-chunks/loop.webm',
        'status' => ChunkStatus::Pending,
    ]);

    Storage::disk('local')->put('live-chunks/loop.webm', 'fake-audio-data');

    $looped = trim(str_repeat('tidak, ', 200));

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')->once()->andReturn(new TranscriptionResult(
        fullText: $looped,
        confidence: 0.4,
        segments: [new TranscriptionSegmentData(text: $looped, startTime: 0.0, endTime: 30.0, confidence: 0.4)],
    ));

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    $chunk->refresh();

    expect($chunk->status)->toBe(ChunkStatus::Completed)
        ->and($chunk->text)->toBe('')
        ->and($chunk->segments)->toBe([]);
});

// A looped chunk must never seed the next chunk's context: doing so teaches the
// model to keep looping, which is how one bad chunk fills a whole sitting.
test('does not feed a looped previous chunk forward as context', function () {
    Event::fake();
    Storage::fake('local');

    app(AiCircuitBreaker::class)->reset(LiveTranscriptionJob::CIRCUIT);

    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => trim(str_repeat('tidak, ', 200)),
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'audio_file_path' => 'live-chunks/next.webm',
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    Storage::disk('local')->put('live-chunks/next.webm', 'fake-audio-data');

    $captured = ['prompt' => 'unset'];

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function (string $path, array $options) use (&$captured) {
            $captured = $options;

            return new TranscriptionResult(fullText: 'a fresh clean sentence', confidence: null, segments: []);
        });

    (new LiveTranscriptionJob($chunk))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($captured['prompt'])->toBeNull();
});
