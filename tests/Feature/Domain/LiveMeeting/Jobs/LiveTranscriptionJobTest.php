<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
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
    $job->handle($mockTranscriber);

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
    $job->handle($mockTranscriber);

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
    $job->handle($mockTranscriber);

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
    $job->handle($mockTranscriber);

    $chunk->refresh();
    expect($chunk->text)->toBe('Bonjour');
});
