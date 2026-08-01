<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Domain\AI\Services\AiUsageRecorder;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's current speech-to-text family.
 *
 * One endpoint serves both models this app uses; they differ in what they give
 * back. `gpt-4o-transcribe-diarize` returns per-segment speaker labels with
 * start/end times via `diarized_json`, which uploaded recordings need. Plain
 * `gpt-transcribe` returns text only and is cheaper, which suits live chunks
 * that already carry their own timing from the session.
 */
class OpenAiTranscriber implements TranscriberInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    /** Audio beyond this length must declare a chunking strategy. */
    private const CHUNKING_THRESHOLD_SECONDS = 30;

    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
        private string $model,
        private bool $diarize,
    ) {}

    /**
     * @param  array{languages?: array<string>, language?: string, keywords?: array<string>, prompt?: string, duration_seconds?: float|int}  $options
     */
    public function transcribe(string $filePath, array $options = []): TranscriptionResult
    {
        $start = microtime(true);

        $response = Http::withToken($this->config['api_key'])
            ->timeout(600)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post(self::ENDPOINT, $this->payload($options));

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            app(AiUsageRecorder::class)->recordTranscription('openai', $this->model, 0, $elapsedMs, 'error');

            $error = $response->json('error.message', __('Transcription request failed with status :status', ['status' => $response->status()]));

            if ($this->isQuotaFailure($response->status(), $response->json('error.code'), $response->json('error.type'))) {
                throw AiQuotaExceededException::make($error);
            }

            throw new \RuntimeException($error);
        }

        $data = $response->json();

        $audioSeconds = (float) ($data['duration']
            ?? $data['usage']['seconds']
            ?? $options['duration_seconds']
            ?? 0);

        app(AiUsageRecorder::class)->recordTranscription(
            provider: 'openai',
            model: $this->model,
            audioSeconds: $audioSeconds,
            durationMs: $elapsedMs,
            inputTokens: (int) ($data['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($data['usage']['output_tokens'] ?? 0),
        );

        $segments = $this->diarize
            ? $this->parseDiarizedSegments($data)
            : $this->parsePlainSegment($data, $audioSeconds);

        return new TranscriptionResult(
            fullText: $this->fullTextFrom($data, $segments),
            confidence: 0.0,
            segments: $segments,
            language: $this->detectedLanguage($data, $options),
            durationSeconds: $audioSeconds > 0 ? (int) round($audioSeconds) : null,
        );
    }

    public function supportsDiarization(): bool
    {
        return $this->diarize;
    }

    /**
     * Both models detect the language themselves and accept hints rather than a
     * fixed choice, so this list is advisory — it mirrors what the UI offers.
     *
     * @return array<string>
     */
    public function supportedLanguages(): array
    {
        return ['en', 'ms', 'zh', 'ta', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'hi'];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(array $options): array
    {
        $payload = [
            'model' => $this->model,
            'response_format' => $this->diarize ? 'diarized_json' : 'json',
        ];

        // The diarizing model rejects every context hint outright — `languages`,
        // `keywords` and `prompt` all return 400. Verified against the live API.
        if (! $this->diarize) {
            $languages = $this->languageHints($options);

            if ($languages !== []) {
                $payload['languages'] = $languages;
            }

            if (! empty($options['keywords'])) {
                $payload['keywords'] = $this->sanitiseKeywords($options['keywords']);
            }

            if (! empty($options['prompt'])) {
                $payload['prompt'] = $options['prompt'];
            }
        }

        // Server-side voice-activity chunking keeps long recordings from being
        // split mid-sentence, and the diarizing model requires it past 30s.
        if ((float) ($options['duration_seconds'] ?? 0) > self::CHUNKING_THRESHOLD_SECONDS || $this->diarize) {
            $payload['chunking_strategy'] = 'auto';
        }

        return $payload;
    }

    /**
     * Meetings here routinely code-switch between Malay and English, so the
     * caller's single language is sent as one hint among several rather than as
     * an exclusive choice.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    private function languageHints(array $options): array
    {
        $languages = $options['languages'] ?? [];

        if ($languages === [] && ! empty($options['language'])) {
            $languages = [$options['language']];
        }

        return array_values(array_unique(array_filter((array) $languages)));
    }

    /**
     * Keyword hints must be single-line and free of angle brackets.
     *
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function sanitiseKeywords(array $keywords): array
    {
        $cleaned = [];

        foreach ($keywords as $keyword) {
            $keyword = trim(str_replace(['<', '>', "\r", "\n"], ' ', (string) $keyword));

            if ($keyword !== '') {
                $cleaned[] = $keyword;
            }
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<TranscriptionSegmentData>
     */
    private function parseDiarizedSegments(array $data): array
    {
        $segments = [];

        foreach ($data['segments'] ?? [] as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $segments[] = new TranscriptionSegmentData(
                text: $text,
                startTime: (float) ($segment['start'] ?? 0),
                endTime: (float) ($segment['end'] ?? 0),
                speaker: $this->speakerLabel($segment['speaker'] ?? null),
                confidence: null,
            );
        }

        return $segments;
    }

    /**
     * Without diarization the whole response is one utterance; the caller knows
     * where it sits on the meeting timeline.
     *
     * @param  array<string, mixed>  $data
     * @return array<TranscriptionSegmentData>
     */
    private function parsePlainSegment(array $data, float $audioSeconds): array
    {
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            return [];
        }

        return [new TranscriptionSegmentData(
            text: $text,
            startTime: 0.0,
            endTime: $audioSeconds,
            speaker: null,
            confidence: null,
        )];
    }

    /**
     * The API labels unknown speakers "A", "B", … — expand those to the
     * "Speaker A" form the rest of the app displays, but leave real names
     * (returned when speaker references were supplied) untouched.
     */
    private function speakerLabel(?string $speaker): ?string
    {
        $speaker = trim((string) $speaker);

        if ($speaker === '') {
            return null;
        }

        return preg_match('/^[A-Z]$/', $speaker) === 1
            ? __('Speaker :label', ['label' => $speaker])
            : $speaker;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<TranscriptionSegmentData>  $segments
     */
    private function fullTextFrom(array $data, array $segments): string
    {
        $text = trim((string) ($data['text'] ?? ''));

        if ($text !== '') {
            return $text;
        }

        return implode(' ', array_map(fn (TranscriptionSegmentData $s) => $s->text, $segments));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function detectedLanguage(array $data, array $options): ?string
    {
        if (! empty($data['language']) && is_string($data['language'])) {
            return $data['language'];
        }

        // gpt-transcribe reports detection as [{"code": "en"}, …].
        $first = $data['languages'][0] ?? null;

        if (is_array($first) && isset($first['code'])) {
            return (string) $first['code'];
        }

        if (is_string($first)) {
            return $first;
        }

        return $options['language'] ?? null;
    }

    /**
     * Quota and rate-limit refusals are standing conditions; the circuit breaker
     * needs to tell them apart from ordinary failures.
     */
    private function isQuotaFailure(int $status, ?string $code, ?string $type): bool
    {
        return $status === 429
            || $code === 'insufficient_quota'
            || $type === 'insufficient_quota';
    }
}
