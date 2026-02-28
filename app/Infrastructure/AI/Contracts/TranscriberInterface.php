<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Contracts;

use App\Infrastructure\AI\DTOs\TranscriptionResult;

interface TranscriberInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function transcribe(string $filePath, array $options = []): TranscriptionResult;

    public function supportsDiarization(): bool;

    /** @return array<string> */
    public function supportedLanguages(): array;
}
