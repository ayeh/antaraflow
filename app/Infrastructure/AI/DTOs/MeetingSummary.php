<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class MeetingSummary
{
    public function __construct(
        public string $summary,
        public string $keyPoints,
        public float $confidenceScore,
    ) {}
}
