<?php

declare(strict_types=1);

use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use App\Infrastructure\AI\Providers\OpenAiTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function audioFixture(): string
{
    Storage::fake('local');
    Storage::disk('local')->put('sample.webm', 'fake-audio');

    return Storage::disk('local')->path('sample.webm');
}

function diarizeTranscriber(): OpenAiTranscriber
{
    return new OpenAiTranscriber(['api_key' => 'sk-test'], 'gpt-4o-transcribe-diarize', true);
}

function plainTranscriber(): OpenAiTranscriber
{
    return new OpenAiTranscriber(['api_key' => 'sk-test'], 'gpt-transcribe', false);
}

it('maps diarized segments to speakers and timings', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'Selamat pagi semua. Terima kasih.',
            'duration' => 12.5,
            'segments' => [
                ['start' => 0.0, 'end' => 4.2, 'text' => 'Selamat pagi semua.', 'speaker' => 'A'],
                ['start' => 4.5, 'end' => 12.5, 'text' => 'Terima kasih.', 'speaker' => 'B'],
            ],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 40],
        ]),
    ]);

    $result = diarizeTranscriber()->transcribe(audioFixture());

    expect($result->segments)->toHaveCount(2)
        ->and($result->segments[0]->speaker)->toBe('Speaker A')
        ->and($result->segments[0]->startTime)->toBe(0.0)
        ->and($result->segments[0]->endTime)->toBe(4.2)
        ->and($result->segments[1]->speaker)->toBe('Speaker B')
        ->and($result->fullText)->toBe('Selamat pagi semua. Terima kasih.')
        ->and($result->durationSeconds)->toBe(13);
});

it('keeps real speaker names untouched', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'Setuju.',
            'duration' => 2.0,
            'segments' => [
                ['start' => 0.0, 'end' => 2.0, 'text' => 'Setuju.', 'speaker' => 'Siti Aminah'],
            ],
        ]),
    ]);

    $result = diarizeTranscriber()->transcribe(audioFixture());

    expect($result->segments[0]->speaker)->toBe('Siti Aminah');
});

it('returns a single untimed segment for the plain model', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'Ini chunk live.',
            'usage' => ['seconds' => 30],
        ]),
    ]);

    $result = plainTranscriber()->transcribe(audioFixture());

    expect($result->segments)->toHaveCount(1)
        ->and($result->segments[0]->speaker)->toBeNull()
        ->and($result->segments[0]->endTime)->toBe(30.0)
        ->and($result->fullText)->toBe('Ini chunk live.');
});

it('sends language hints, keywords and a chunking strategy', function (): void {
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'ok', 'duration' => 5])]);

    diarizeTranscriber()->transcribe(audioFixture(), [
        'languages' => ['ms', 'en'],
        'keywords' => ['Ahmad Faiz', 'ePengambilan', '  ', 'Ahmad Faiz'],
    ]);

    Http::assertSent(function (Request $request) {
        $parts = collect($request->data())->groupBy('name')
            ->map(fn ($group) => $group->pluck('contents')->all());

        return $parts['model'] === ['gpt-4o-transcribe-diarize']
            && $parts['response_format'] === ['diarized_json']
            && $parts['languages[]'] === ['ms', 'en']
            && $parts['keywords[]'] === ['Ahmad Faiz', 'ePengambilan']
            && $parts['chunking_strategy'] === ['auto'];
    });
});

it('falls back to the single language option when no hints are given', function (): void {
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'ok', 'duration' => 5])]);

    plainTranscriber()->transcribe(audioFixture(), ['language' => 'ms']);

    Http::assertSent(function (Request $request) {
        $parts = collect($request->data())->groupBy('name')
            ->map(fn ($group) => $group->pluck('contents')->all());

        return $parts['languages[]'] === ['ms']
            && $parts['response_format'] === ['json'];
    });
});

it('raises a quota exception so the circuit breaker can trip', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'error' => ['message' => 'You exceeded your current quota', 'code' => 'insufficient_quota'],
        ], 429),
    ]);

    expect(fn () => plainTranscriber()->transcribe(audioFixture()))
        ->toThrow(AiQuotaExceededException::class, 'exceeded your current quota');
});

it('raises a plain runtime exception for other failures', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response(['error' => ['message' => 'Bad request']], 400),
    ]);

    expect(fn () => plainTranscriber()->transcribe(audioFixture()))
        ->toThrow(RuntimeException::class, 'Bad request');
});
