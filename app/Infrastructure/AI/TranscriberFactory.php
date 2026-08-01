<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Domain\Admin\Services\AiControlService;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\Providers\DisabledTranscriber;
use App\Infrastructure\AI\Providers\OpenAiTranscriber;
use App\Infrastructure\AI\Providers\OpenAIWhisperTranscriber;
use App\Infrastructure\AI\Providers\WhisperLocalTranscriber;
use App\Support\Enums\TranscriptionMode;

/**
 * Picks the transcriber for a given job.
 *
 * Uploads and live chunks want different things from the provider, so they get
 * different models rather than a single global binding. Keeping the choice here
 * means swapping a model — when OpenAI ships a successor to the diarizer, say —
 * is a config change rather than an edit to every calling job.
 */
class TranscriberFactory
{
    public function __construct(
        private readonly AiControlService $aiControl,
    ) {}

    public function for(TranscriptionMode $mode): TranscriberInterface
    {
        if (! $this->aiControl->isEnabled()) {
            return new DisabledTranscriber;
        }

        $transcriber = config('ai.transcriber', 'openai');

        if ($transcriber === 'whisper_local') {
            return new WhisperLocalTranscriber(config('ai.providers.whisper_local', []));
        }

        $config = config('ai.providers.openai', []);
        $model = $this->modelFor($mode, $config);

        // The legacy Whisper endpoint has a different response shape, so it
        // keeps its own provider rather than being folded into the new one.
        if ($model === 'whisper-1') {
            return new OpenAIWhisperTranscriber([...$config, 'transcription_model' => $model]);
        }

        return new OpenAiTranscriber($config, $model, $this->diarizes($mode, $model));
    }

    /** @param array<string, mixed> $config */
    private function modelFor(TranscriptionMode $mode, array $config): string
    {
        return match ($mode) {
            TranscriptionMode::Upload => $config['upload_transcription_model'] ?? 'gpt-4o-transcribe-diarize',
            TranscriptionMode::Live => $config['live_transcription_model'] ?? 'gpt-transcribe',
        };
    }

    /**
     * Only ask for diarization where the transcript will show speakers and the
     * configured model can actually produce them.
     */
    private function diarizes(TranscriptionMode $mode, string $model): bool
    {
        return $mode === TranscriptionMode::Upload && str_contains($model, 'diarize');
    }
}
