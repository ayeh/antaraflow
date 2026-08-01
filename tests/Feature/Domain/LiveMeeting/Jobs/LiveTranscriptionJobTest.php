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
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
