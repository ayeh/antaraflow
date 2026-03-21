<?php

declare(strict_types=1);

use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Jobs\ProcessTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('job processes transcription and creates segments', function () {
    Event::fake();

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturn(new TranscriptionResult(
            fullText: 'Hello world',
            confidence: 0.95,
            segments: [
                new TranscriptionSegmentData(text: 'Hello', startTime: 0.0, endTime: 1.0, speaker: 'Speaker 1', confidence: 0.95),
                new TranscriptionSegmentData(text: 'world', startTime: 1.0, endTime: 2.0, speaker: 'Speaker 1', confidence: 0.93),
            ],
        ));

    $this->app->instance(TranscriberInterface::class, $mockTranscriber);

    $transcription = AudioTranscription::factory()->create();

    $job = new ProcessTranscriptionJob($transcription);
    $job->handle($mockTranscriber);

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Hello world')
        ->and($transcription->confidence_score)->toBe(0.95)
        ->and($transcription->completed_at)->not->toBeNull()
        ->and($transcription->segments)->toHaveCount(2);

    Event::assertDispatched(TranscriptionCompleted::class);
});

test('job marks transcription as failed on error', function () {
    Event::fake();

    $transcription = AudioTranscription::factory()->create();

    $job = new ProcessTranscriptionJob($transcription);
    $job->failed(new RuntimeException('Provider unavailable'));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->error_message)->toBe('Provider unavailable')
        ->and($transcription->retry_count)->toBe(1);

    Event::assertDispatched(TranscriptionFailed::class);
});

it('assigns speaker labels based on time gap heuristic', function (): void {
    $segments = [
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Hello', speaker: null, startTime: 0.0, endTime: 2.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'How are you', speaker: null, startTime: 4.0, endTime: 6.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'I am fine', speaker: null, startTime: 8.0, endTime: 10.0, confidence: 0.9
        ),
    ];

    $job = new \App\Domain\Transcription\Jobs\ProcessTranscriptionJob(
        \App\Domain\Transcription\Models\AudioTranscription::factory()->make()
    );

    $result = $job->assignSpeakers($segments);

    expect($result[0]->speaker)->toBe('Speaker 1');
    expect($result[1]->speaker)->toBe('Speaker 2');
    expect($result[2]->speaker)->toBe('Speaker 3');
});

it('keeps same speaker when time gap is below threshold', function (): void {
    $segments = [
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Hello', speaker: null, startTime: 0.0, endTime: 2.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'World', speaker: null, startTime: 2.5, endTime: 4.0, confidence: 0.9
        ),
    ];

    $job = new \App\Domain\Transcription\Jobs\ProcessTranscriptionJob(
        \App\Domain\Transcription\Models\AudioTranscription::factory()->make()
    );

    $result = $job->assignSpeakers($segments);

    expect($result[0]->speaker)->toBe('Speaker 1');
    expect($result[1]->speaker)->toBe('Speaker 1');
});

test('job sets processing status before transcribing', function () {
    $statuses = [];

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function () {
            return new TranscriptionResult(
                fullText: 'Test',
                confidence: 0.9,
                segments: [],
            );
        });

    $transcription = AudioTranscription::factory()->create();

    $job = new ProcessTranscriptionJob($transcription);
    $job->handle($mockTranscriber);

    $transcription->refresh();

    expect($transcription->started_at)->not->toBeNull()
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
});
