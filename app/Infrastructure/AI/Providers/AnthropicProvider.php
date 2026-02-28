<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use RuntimeException;

class AnthropicProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-20250514',
    ) {}

    /** {@inheritDoc} */
    public function chat(string $prompt, array $context = []): string
    {
        throw new RuntimeException('Anthropic provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function summarize(string $text): MeetingSummary
    {
        throw new RuntimeException('Anthropic provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function extractActionItems(string $text): array
    {
        throw new RuntimeException('Anthropic provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function extractDecisions(string $text): array
    {
        throw new RuntimeException('Anthropic provider not yet implemented.');
    }
}
