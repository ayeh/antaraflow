<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class TranscriptionResult
{
    /**
     * @param  array<TranscriptionSegmentData>  $segments
     * @param  float|null  $confidence  Null when the provider reports no score —
     *                                  distinct from a genuine score of zero.
     */
    public function __construct(
        public string $fullText,
        public ?float $confidence,
        public array $segments,
        public ?string $language = null,
        public ?int $durationSeconds = null,
    ) {}
}
