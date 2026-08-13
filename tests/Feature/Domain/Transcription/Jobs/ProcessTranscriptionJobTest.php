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
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

test('job processes transcription and creates segments', function () {
    Event::fake();

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('supportsDiarization')->andReturnFalse();
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
    $job->handle(fakeTranscriberFactory($mockTranscriber));

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
    // Segment 1 → 2: gap = 0.8s (below 1.5s threshold) → same speaker
    // Segment 2 → 3: gap = 2.0s (above 1.5s threshold) → new speaker
    $segments = [
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Hello everyone', speaker: null, startTime: 0.0, endTime: 3.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Good morning', speaker: null, startTime: 3.8, endTime: 5.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Thank you', speaker: null, startTime: 7.0, endTime: 9.0, confidence: 0.9
        ),
    ];

    $job = new \App\Domain\Transcription\Jobs\ProcessTranscriptionJob(
        \App\Domain\Transcription\Models\AudioTranscription::factory()->make()
    );

    $result = $job->assignSpeakers($segments);

    expect($result[0]->speaker)->toBe('Speaker 1'); // start
    expect($result[1]->speaker)->toBe('Speaker 1'); // gap 0.8s — same speaker
    expect($result[2]->speaker)->toBe('Speaker 2'); // gap 2.0s — new speaker
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
    $mockTranscriber->shouldReceive('supportsDiarization')->andReturnFalse();
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
    $job->handle(fakeTranscriberFactory($mockTranscriber));

    $transcription->refresh();

    expect($transcription->started_at)->not->toBeNull()
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
});

it('preprocesses audio with quality filters', function (): void {
    $source = sys_get_temp_dir().'/preprocess_source_'.uniqid().'.wav';

    Process::run([
        'ffmpeg', '-hide_banner', '-loglevel', 'error',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=5',
        '-y', $source,
    ])->throw();

    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->create());
    $processed = $job->preprocessAudio($source);

    try {
        expect($processed)->not->toBe($source)
            ->and(file_exists($processed))->toBeTrue()
            ->and(filesize($processed))->toBeGreaterThan(0);
    } finally {
        @unlink($source);
        if ($processed !== $source) {
            @unlink($processed);
        }
    }
})->skip(fn () => ! ffmpegAvailable(), 'ffmpeg is required for this test.');

it('falls back to the original path when preprocessing fails', function (): void {
    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->create());
    $fakePath = sys_get_temp_dir().'/nonexistent_audio_'.uniqid().'.webm';

    // ffmpeg fails because the input file does not exist — verifies graceful fallback
    $result = $job->preprocessAudio($fakePath);

    expect($result)->toBe($fakePath);
})->skip(fn () => ! ffmpegAvailable(), 'ffmpeg is required for this test.');

it('compresses audio to fit under the size limit', function (): void {
    $source = sys_get_temp_dir().'/compress_source_'.uniqid().'.wav';

    Process::run([
        'ffmpeg', '-hide_banner', '-loglevel', 'error',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=60',
        '-y', $source,
    ])->throw();

    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->create());

    $maxBytes = 200 * 1024;
    $compressed = $job->compressAudio($source, $maxBytes);

    expect(filesize($compressed))->toBeLessThanOrEqual($maxBytes);

    @unlink($source);
    @unlink($compressed);
})->skip(fn () => ! ffmpegAvailable(), 'ffmpeg and ffprobe are required for this test.');

it('fails loudly when audio cannot be compressed under the size limit', function (): void {
    $source = sys_get_temp_dir().'/compress_source_'.uniqid().'.wav';

    Process::run([
        'ffmpeg', '-hide_banner', '-loglevel', 'error',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=60',
        '-y', $source,
    ])->throw();

    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->create());

    try {
        expect(fn () => $job->compressAudio($source, 1_000))
            ->toThrow(RuntimeException::class, 'could not be compressed');
    } finally {
        @unlink($source);
    }
})->skip(fn () => ! ffmpegAvailable(), 'ffmpeg and ffprobe are required for this test.');

it('keeps provider speaker labels when the model diarizes', function (): void {
    Event::fake();

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('supportsDiarization')->andReturnTrue();
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturn(new TranscriptionResult(
            fullText: 'Selamat pagi. Terima kasih.',
            confidence: 0.0,
            segments: [
                // A 5s gap would make the heuristic invent a new speaker here.
                new TranscriptionSegmentData(text: 'Selamat pagi.', startTime: 0.0, endTime: 3.0, speaker: 'Siti'),
                new TranscriptionSegmentData(text: 'Terima kasih.', startTime: 8.0, endTime: 10.0, speaker: 'Siti'),
            ],
        ));

    $transcription = AudioTranscription::factory()->create();

    (new ProcessTranscriptionJob($transcription))->handle(fakeTranscriberFactory($mockTranscriber));

    $speakers = $transcription->refresh()->segments()->orderBy('sequence_order')->pluck('speaker')->all();

    expect($speakers)->toBe(['Siti', 'Siti']);
});

it('sends attendee and meeting names as recognition keywords', function (): void {
    Event::fake();

    $transcription = AudioTranscription::factory()->create();
    $meeting = $transcription->minutesOfMeeting;
    $meeting->update(['title' => 'CR ePengambilan']);
    $meeting->attendees()->create(['name' => 'Ahmad Faiz', 'company' => 'Antara Digital']);

    $captured = [];

    $mockTranscriber = Mockery::mock(TranscriberInterface::class);
    $mockTranscriber->shouldReceive('supportsDiarization')->andReturnFalse();
    $mockTranscriber->shouldReceive('transcribe')
        ->once()
        ->andReturnUsing(function (string $path, array $options) use (&$captured) {
            $captured = $options['keywords'] ?? [];

            return new TranscriptionResult(fullText: 'ok', confidence: 0.0, segments: []);
        });

    (new ProcessTranscriptionJob($transcription))->handle(fakeTranscriberFactory($mockTranscriber));

    expect($captured)->toContain('Ahmad Faiz', 'Antara Digital', 'CR ePengambilan');
});

/*
 * preprocessAudio() falls back to the untouched file when ffmpeg fails, which is
 * right for audio and wrong for video: the fallback would send the pictures too,
 * at the organisation's cost and past the transcriber's size limit.
 */
test('a video whose audio cannot be extracted fails instead of sending the video', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    $transcription = AudioTranscription::factory()->create(['mime_type' => 'video/mp4']);
    $job = new ProcessTranscriptionJob($transcription);

    expect(fn () => $job->preprocessAudio('/tmp/meeting.mp4'))
        ->toThrow(RuntimeException::class);
});

test('audio still falls back to the original when ffmpeg fails', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    $transcription = AudioTranscription::factory()->create(['mime_type' => 'audio/mpeg']);
    $job = new ProcessTranscriptionJob($transcription);

    expect($job->preprocessAudio('/tmp/meeting.mp3'))->toBe('/tmp/meeting.mp3');
});

/*
 * With retry_after below the job's runtime, a second worker reclaims a job that
 * is merely slow and transcribes the same recording again — paying OpenAI twice.
 * That was 58% of the Whisper bill before it was caught, so the relationship
 * between the two numbers is asserted rather than left to a comment.
 */
test('the queue cannot reclaim a transcription while it is still running', function () {
    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->make());

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout);
});

test('a failed transcription is not retried, because every attempt is billed', function () {
    $job = new ProcessTranscriptionJob(AudioTranscription::factory()->make());

    expect($job->tries)->toBe(1)
        ->and($job->failOnTimeout)->toBeTrue();
});
