<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Domain\AI\Services\AiUsageRecorder;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Support\Facades\Http;

class OpenAIWhisperTranscriber implements TranscriberInterface
{
    /** Roughly the 224-token prompt ceiling, kept well short of it. */
    private const PROMPT_CHAR_BUDGET = 600;

    /**
     * Only discard a segment when Whisper is all but certain it heard nothing.
     *
     * This sat at 0.7, which far-field meeting audio trips constantly on real
     * speech: measured across two live sessions it silently dropped a third of
     * every recording, and re-running the same audio through another model
     * recovered whole sentences from chunks stored as empty.
     */
    private const SILENCE_CERTAINTY = 0.95;

    /**
     * Phrases Whisper emits from silence rather than from speech. Matched
     * against a whole segment, so a meeting genuinely ending in "thank you for
     * watching" would lose only that segment.
     *
     * @var array<int, string>
     */
    private const HALLUCINATIONS = [
        'terima kasih kerana menonton',
        'terima kasih kerana menonton!',
        'terima kasih atas dukungan anda',
        'berita terkini terima kasih atas dukungan anda',
        'subscribe',
        'like and subscribe',
        'thank you for watching',
        'thanks for watching',
        'you',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
    ) {}

    public function transcribe(string $filePath, array $options = []): TranscriptionResult
    {
        $model = $this->config['transcription_model'] ?? 'whisper-1';
        $start = microtime(true);

        $payload = [
            'model' => $model,
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['segment'],
            'temperature' => 0,
        ];

        // Whisper takes vocabulary hints as free text rather than a keyword
        // list. Feeding it the attendee names is what turns "Epam Gambilan"
        // into "ePengambilan" — measured against the live API.
        $prompt = $this->vocabularyPrompt($options);

        if ($prompt !== null) {
            $payload['prompt'] = $prompt;
        }

        // `language` is deliberately omitted: it accepts only one code, and
        // these meetings switch between Malay and English mid-sentence, so
        // auto-detection beats committing to whichever code was stored.
        $response = Http::withToken($this->config['api_key'])
            ->timeout(300)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', $payload);

        if ($response->failed()) {
            app(AiUsageRecorder::class)->recordTranscription('openai', $model, 0, (int) round((microtime(true) - $start) * 1000), 'error');
            $error = $response->json('error.message', __('Whisper API request failed with status :status', ['status' => $response->status()]));

            if ($this->isQuotaFailure($response->status(), $response->json('error.code'), $response->json('error.type'))) {
                throw AiQuotaExceededException::make($error);
            }

            throw new \RuntimeException($error);
        }

        $data = $response->json();

        app(AiUsageRecorder::class)->recordTranscription(
            provider: 'openai',
            model: $model,
            audioSeconds: (float) ($data['duration'] ?? 0),
            durationMs: (int) round((microtime(true) - $start) * 1000),
        );

        $segments = [];
        foreach ($data['segments'] ?? [] as $segment) {
            $noSpeechProb = (float) ($segment['no_speech_prob'] ?? 0);

            if ($noSpeechProb > self::SILENCE_CERTAINTY) {
                continue;
            }

            $text = trim($segment['text'] ?? '');

            if ($text === '' || $this->isHallucination($text)) {
                continue;
            }

            $segments[] = new TranscriptionSegmentData(
                text: $text,
                speaker: null,
                startTime: (float) ($segment['start'] ?? 0),
                endTime: (float) ($segment['end'] ?? 0),
                confidence: (float) ($segment['avg_logprob'] ?? 0),
            );
        }

        // Rebuild full text from filtered segments to exclude hallucinated content
        $fullText = empty($segments)
            ? ''
            : implode(' ', array_map(fn (TranscriptionSegmentData $s) => trim($s->text), $segments));

        return new TranscriptionResult(
            fullText: $fullText,
            segments: $segments,
            language: $data['language'] ?? $options['language'] ?? 'en',
            confidence: $this->calculateAverageConfidence($segments),
        );
    }

    public function supportsDiarization(): bool
    {
        return false;
    }

    /**
     * Whisper's prompt is capped at 224 tokens, so the hint list is trimmed to
     * a conservative character budget rather than sent whole. People's names go
     * in first — those are the errors a reader actually notices.
     *
     * @param  array<string, mixed>  $options
     */
    private function vocabularyPrompt(array $options): ?string
    {
        $parts = [];

        $keywords = $this->keywordsWithinBudget($options['keywords'] ?? []);

        if ($keywords !== []) {
            $parts[] = __('Meeting transcript. Names and terms: :keywords.', [
                'keywords' => implode(', ', $keywords),
            ]);
        }

        // Whisper has one hint channel, so preceding context shares it with the
        // vocabulary rather than replacing it.
        if (! empty($options['prompt'])) {
            $parts[] = trim((string) $options['prompt']);
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(' ', $parts), 0, self::PROMPT_CHAR_BUDGET);
    }

    /** Whether the whole segment is a phrase Whisper invents from silence. */
    private function isHallucination(string $text): bool
    {
        return in_array(mb_strtolower($text), self::HALLUCINATIONS, true);
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function keywordsWithinBudget(array $keywords): array
    {
        $kept = [];
        $length = 0;

        foreach ($keywords as $keyword) {
            $keyword = trim(preg_replace('/\s+/', ' ', (string) $keyword) ?? '');

            if ($keyword === '' || in_array($keyword, $kept, true)) {
                continue;
            }

            $length += mb_strlen($keyword) + 2;

            if ($length > self::PROMPT_CHAR_BUDGET) {
                break;
            }

            $kept[] = $keyword;
        }

        return $kept;
    }

    /**
     * Whether the provider rejected the call for quota or rate-limit reasons,
     * which retrying on our own schedule cannot resolve.
     */
    private function isQuotaFailure(int $status, ?string $code, ?string $type): bool
    {
        return $status === 429
            || $code === 'insufficient_quota'
            || $type === 'insufficient_quota';
    }

    /** @return array<string> */
    public function supportedLanguages(): array
    {
        return ['en', 'ms', 'zh', 'ta', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'hi'];
    }

    /** @param array<TranscriptionSegmentData> $segments */
    private function calculateAverageConfidence(array $segments): ?float
    {
        if (empty($segments)) {
            return null;
        }

        $total = array_sum(array_map(fn (TranscriptionSegmentData $s) => $s->confidence, $segments));

        return $total / count($segments);
    }
}
