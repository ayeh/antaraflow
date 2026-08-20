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

    plainTranscriber()->transcribe(audioFixture(), [
        'languages' => ['ms', 'en'],
        'keywords' => ['Ahmad Faiz', 'ePengambilan', '  ', 'Ahmad Faiz'],
        'prompt' => 'A project status meeting.',
        'duration_seconds' => 120,
    ]);

    Http::assertSent(function (Request $request) {
        $parts = collect($request->data())->groupBy('name')
            ->map(fn ($group) => $group->pluck('contents')->all());

        return $parts['model'] === ['gpt-transcribe']
            && $parts['response_format'] === ['json']
            && $parts['languages[]'] === ['ms', 'en']
            && $parts['keywords[]'] === ['Ahmad Faiz', 'ePengambilan']
            && $parts['prompt'] === ['A project status meeting.']
            && $parts['chunking_strategy'] === ['auto'];
    });
});

it('withholds context hints the diarizing model rejects', function (): void {
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'ok', 'duration' => 5])]);

    diarizeTranscriber()->transcribe(audioFixture(), [
        'languages' => ['ms', 'en'],
        'keywords' => ['Ahmad Faiz'],
        'prompt' => 'A project status meeting.',
    ]);

    // Verified against the live API: languages/keywords/prompt each 400 on
    // gpt-4o-transcribe-diarize, so sending them breaks every upload.
    Http::assertSent(function (Request $request) {
        $names = collect($request->data())->pluck('name')->all();

        return ! in_array('languages[]', $names, true)
            && ! in_array('keywords[]', $names, true)
            && ! in_array('prompt', $names, true)
            && in_array('chunking_strategy', $names, true);
    });
});

it('reads the detected language from the gpt-transcribe response shape', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'ok',
            'languages' => [['code' => 'en']],
            'usage' => ['type' => 'duration', 'seconds' => 9],
        ]),
    ]);

    expect(plainTranscriber()->transcribe(audioFixture())->language)->toBe('en');
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

function whisperTranscriber(): App\Infrastructure\AI\Providers\OpenAIWhisperTranscriber
{
    return new App\Infrastructure\AI\Providers\OpenAIWhisperTranscriber([
        'api_key' => 'sk-test',
        'transcription_model' => 'whisper-1',
    ]);
}

it('reports no confidence when the provider does not supply one', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'ok',
            'duration' => 5,
            'segments' => [['start' => 0, 'end' => 5, 'text' => 'ok', 'speaker' => 'A']],
        ]),
    ]);

    $result = diarizeTranscriber()->transcribe(audioFixture());

    // Null, not 0.0 — "unknown" and "zero percent confident" are different claims.
    expect($result->confidence)->toBeNull()
        ->and($result->segments[0]->confidence)->toBeNull();
});

it('reports no confidence when whisper returns no usable segments', function (): void {
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => '', 'duration' => 5, 'segments' => []])]);

    expect(whisperTranscriber()->transcribe(audioFixture())->confidence)->toBeNull();
});

it('keeps speech that whisper is merely unsure about', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => '',
            'duration' => 30,
            'segments' => [
                // Far-field meeting audio routinely lands in this band on real
                // speech; the old 0.7 cut discarded a third of every recording.
                ['start' => 0, 'end' => 10, 'text' => 'Tapi kalau anak-anak dia tak buat.', 'no_speech_prob' => 0.82, 'avg_logprob' => -0.4],
                ['start' => 10, 'end' => 20, 'text' => 'Memang pernah dia buat.', 'no_speech_prob' => 0.91, 'avg_logprob' => -0.5],
                ['start' => 20, 'end' => 30, 'text' => 'Silence here.', 'no_speech_prob' => 0.98, 'avg_logprob' => -0.9],
            ],
        ]),
    ]);

    $result = whisperTranscriber()->transcribe(audioFixture());

    expect($result->segments)->toHaveCount(2)
        ->and($result->fullText)->toContain('Tapi kalau anak-anak')
        ->and($result->fullText)->toContain('Memang pernah dia buat')
        ->and($result->fullText)->not->toContain('Silence here');
});

it('still drops phrases whisper invents from silence', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => '',
            'duration' => 30,
            'segments' => [
                ['start' => 0, 'end' => 15, 'text' => 'Berita terkini terima kasih atas dukungan anda', 'no_speech_prob' => 0.6],
                ['start' => 15, 'end' => 30, 'text' => 'Okay kita mula sekarang.', 'no_speech_prob' => 0.6],
            ],
        ]),
    ]);

    $result = whisperTranscriber()->transcribe(audioFixture());

    expect($result->segments)->toHaveCount(1)
        ->and($result->fullText)->toBe('Okay kita mula sekarang.');
});

it('drops a looped segment but keeps the real speech around it', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => '',
            'duration' => 30,
            'segments' => [
                ['start' => 0, 'end' => 5, 'text' => 'Selamat pagi semua, kita mulakan mesyuarat.', 'no_speech_prob' => 0.1],
                ['start' => 5, 'end' => 25, 'text' => trim(str_repeat('tidak, ', 40)), 'no_speech_prob' => 0.2],
                ['start' => 25, 'end' => 30, 'text' => 'Baik, kita teruskan ke item seterusnya.', 'no_speech_prob' => 0.1],
            ],
        ]),
    ]);

    $result = whisperTranscriber()->transcribe(audioFixture());

    expect($result->segments)->toHaveCount(2)
        ->and($result->fullText)->toBe('Selamat pagi semua, kita mulakan mesyuarat. Baik, kita teruskan ke item seterusnya.');
});

it('discards a whisper transcript that is nothing but a loop across one-word segments', function (): void {
    $segments = [];
    foreach (range(0, 200) as $i) {
        $segments[] = ['start' => $i, 'end' => $i + 1, 'text' => $i % 2 === 0 ? 'Tidak.' : 'Okey.', 'no_speech_prob' => 0.2];
    }

    Http::fake(['*/audio/transcriptions' => Http::response(['text' => '', 'duration' => 200, 'segments' => $segments])]);

    $result = whisperTranscriber()->transcribe(audioFixture());

    expect($result->fullText)->toBe('')
        ->and($result->segments)->toBe([]);
});

it('discards a diarized transcript that is a single looped word', function (): void {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => trim(str_repeat('tidak, ', 300)),
            'duration' => 120,
            'segments' => [
                ['start' => 0, 'end' => 60, 'text' => trim(str_repeat('tidak, ', 150)), 'speaker' => 'A'],
                ['start' => 60, 'end' => 120, 'text' => trim(str_repeat('tidak, ', 150)), 'speaker' => 'A'],
            ],
        ]),
    ]);

    $result = diarizeTranscriber()->transcribe(audioFixture());

    expect($result->fullText)->toBe('')
        ->and($result->segments)->toBe([]);
});

it('never sends whisper a prompt, whatever hints it is given', function (): void {
    Http::fake(['*/audio/transcriptions' => Http::response(['text' => 'ok', 'duration' => 5, 'segments' => []])]);

    whisperTranscriber()->transcribe(audioFixture(), [
        'keywords' => ['Ahmad Faiz', 'ePengambilan'],
        'prompt' => '...sambungan perbincangan tadi',
        'language' => 'ms',
    ]);

    // Measured on twelve minutes of real meeting audio: any prompt sent
    // Whisper into a repetition loop that replaced the speech itself.
    Http::assertSent(function (Request $request) {
        $names = collect($request->data())->pluck('name')->all();

        return ! in_array('prompt', $names, true)
            && ! in_array('keywords[]', $names, true)
            && ! in_array('language', $names, true);
    });
});
