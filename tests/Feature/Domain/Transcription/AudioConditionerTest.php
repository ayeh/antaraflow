<?php

declare(strict_types=1);

use App\Domain\Transcription\Services\AudioConditioner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

test('lifts quiet audio and caps the peak so the lift cannot clip', function () {
    Process::fake();

    app(AudioConditioner::class)->condition('/tmp/quiet.wav');

    Process::assertRan(function ($process) {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, 'highpass=f=80')
            && str_contains($command, 'loudnorm=I=-16')
            && str_contains($command, 'TP=-1.5');
    });
});

test('hands the transcriber mono at the rate speech recognition wants', function () {
    Process::fake();

    app(AudioConditioner::class)->condition('/tmp/in.wav');

    Process::assertRan(function ($process) {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, '-ar 16000') && str_contains($command, '-ac 1');
    });
});

// Untreated audio transcribes worse. No audio transcribes not at all — so a
// missing or broken ffmpeg must not take the recording down with it.
test('returns null rather than throwing when ffmpeg fails', function () {
    Log::spy();
    Process::fake(['*' => Process::result(output: '', errorOutput: 'not found', exitCode: 127)]);

    expect(app(AudioConditioner::class)->condition('/tmp/in.wav'))->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'using the original file'));
});

test('carries the caller context into the warning so a bad chunk is findable', function () {
    Log::spy();
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    app(AudioConditioner::class)->condition('/tmp/in.wav', logContext: ['chunk_id' => 4242]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $_, array $context) => ($context['chunk_id'] ?? null) === 4242);
});
