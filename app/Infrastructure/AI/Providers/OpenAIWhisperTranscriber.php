<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Domain\AI\Services\AiUsageRecorder;
use App\Domain\Transcription\Services\RepetitionGuard;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Support\Facades\Http;

class OpenAIWhisperTranscriber implements TranscriberInterface
{
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

        /*
         * No prompt is sent, deliberately.
         *
         * Whisper takes vocabulary hints as free text, and on a clean sample
         * that fixed proper nouns outright. On real meeting audio it does the
         * opposite: measured over twelve minutes of a recorded meeting, a
         * twelve-name hint made Whisper emit "nama-nama dan nama-nama dan …"
         * for the first ninety seconds in place of the speech, and a four-name
         * hint collapsed the whole transcript into repeated ellipses. The same
         * audio with no prompt transcribed cleanly.
         *
         * Hints reach the models that accept them properly — see OpenAiTranscriber.
         */

        // `language` is deliberately omitted: it accepts only one code, and
        // these meetings switch between Malay and English mid-sentence, so
        // auto-detection beats committing to whichever code was stored.
        $response = Http::withToken($this->config['api_key'])
            ->timeout(600)
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

        $repetition = app(RepetitionGuard::class);

        $segments = [];
        foreach ($data['segments'] ?? [] as $segment) {
            $noSpeechProb = (float) ($segment['no_speech_prob'] ?? 0);

            if ($noSpeechProb > self::SILENCE_CERTAINTY) {
                continue;
            }

            $text = trim($segment['text'] ?? '');

            // A silent stretch amplified to the speech floor comes back as one
            // word looped for the whole segment. Drop those here so a partly
            // real recording keeps its speech and loses only the dead air.
            if ($text === '' || $this->isHallucination($text) || $repetition->isDegenerate($text)) {
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

        // A loop split across one-word segments survives the per-segment drop but
        // collapses the transcript as a whole — a recording of near silence comes
        // back as nothing but "tidak" and "okey". Discard it wholesale.
        if ($fullText !== '' && $repetition->isDegenerate($fullText)) {
            $segments = [];
            $fullText = '';
        }

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

    /** Whether the whole segment is a phrase Whisper invents from silence. */
    private function isHallucination(string $text): bool
    {
        return in_array(mb_strtolower($text), self::HALLUCINATIONS, true);
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
