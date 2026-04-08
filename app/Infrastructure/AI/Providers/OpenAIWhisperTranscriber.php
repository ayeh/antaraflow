<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use Illuminate\Support\Facades\Http;

class OpenAIWhisperTranscriber implements TranscriberInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
    ) {}

    public function transcribe(string $filePath, array $options = []): TranscriptionResult
    {
        $response = Http::withToken($this->config['api_key'])
            ->timeout(300)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $this->config['transcription_model'] ?? 'whisper-1',
                'language' => $options['language'] ?? null,
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['segment'],
                'temperature' => 0,
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Whisper API request failed with status '.$response->status());
            throw new \RuntimeException($error);
        }

        $data = $response->json();

        // Known Whisper hallucinations for silence/low-quality audio
        $hallucinations = [
            'terima kasih kerana menonton',
            'terima kasih kerana menonton!',
            'subscribe',
            'like and subscribe',
            'thank you for watching',
            'thanks for watching',
            'you',
        ];

        $segments = [];
        foreach ($data['segments'] ?? [] as $segment) {
            // Skip segments where Whisper detects mostly silence
            $noSpeechProb = (float) ($segment['no_speech_prob'] ?? 0);
            if ($noSpeechProb > 0.7) {
                continue;
            }

            $text = trim($segment['text'] ?? '');

            // Skip known hallucination phrases
            if (in_array(mb_strtolower($text), $hallucinations, true)) {
                continue;
            }

            if ($text === '') {
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

    /** @return array<string> */
    public function supportedLanguages(): array
    {
        return ['en', 'ms', 'zh', 'ta', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'hi'];
    }

    /** @param array<TranscriptionSegmentData> $segments */
    private function calculateAverageConfidence(array $segments): float
    {
        if (empty($segments)) {
            return 0.0;
        }

        $total = array_sum(array_map(fn (TranscriptionSegmentData $s) => $s->confidence, $segments));

        return $total / count($segments);
    }
}
