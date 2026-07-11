<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\Exceptions\AiDisabledException;

/**
 * Null-object transcriber bound when AI is disabled platform-wide.
 */
class DisabledTranscriber implements TranscriberInterface
{
    /** @param array<string, mixed> $options */
    public function transcribe(string $filePath, array $options = []): TranscriptionResult
    {
        throw AiDisabledException::make();
    }

    public function supportsDiarization(): bool
    {
        return false;
    }

    /** @return array<string> */
    public function supportedLanguages(): array
    {
        return [];
    }
}
