<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class ExtractedDecision
{
    public function __construct(
        public string $decision,
        public ?string $context = null,
        public ?string $madeBy = null,
    ) {}
}
