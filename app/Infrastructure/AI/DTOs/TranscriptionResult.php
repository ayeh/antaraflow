<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class TranscriptionResult
{
    /**
     * @param  array<TranscriptionSegmentData>  $segments
     */
    public function __construct(
        public string $fullText,
        public float $confidence,
        public array $segments,
        public ?string $language = null,
        public ?int $durationSeconds = null,
    ) {}
}
