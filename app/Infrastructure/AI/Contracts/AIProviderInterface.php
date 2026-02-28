<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Contracts;

use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;

interface AIProviderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function chat(string $prompt, array $context = []): string;

    public function summarize(string $text): MeetingSummary;

    /** @return array<ExtractedActionItem> */
    public function extractActionItems(string $text): array;

    /** @return array<ExtractedDecision> */
    public function extractDecisions(string $text): array;
}
