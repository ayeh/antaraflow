<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class ExtractedActionItem
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $assignee = null,
        public ?string $dueDate = null,
        public string $priority = 'medium',
    ) {}
}
