<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class TranscriptionSegmentData
{
    public function __construct(
        public string $text,
        public float $startTime,
        public float $endTime,
        public ?string $speaker = null,
        public ?float $confidence = null,
    ) {}
}
