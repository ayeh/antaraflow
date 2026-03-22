<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class ExtractedRisk
{
    public function __construct(
        public string $risk,
        public string $severity = 'medium',
        public ?string $mitigation = null,
        public ?string $raisedBy = null,
    ) {}
}
