<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\Exceptions\AiDisabledException;

/**
 * Null-object provider bound when AI is disabled platform-wide.
 * Every operation fails fast with a clear, catchable exception instead
 * of reaching a paid API.
 */
class DisabledAIProvider implements AIProviderInterface
{
    /** @param array<string, mixed> $context */
    public function chat(string $prompt, array $context = []): string
    {
        throw AiDisabledException::make();
    }

    public function summarize(string $text, ?string $language = null): MeetingSummary
    {
        throw AiDisabledException::make();
    }

    /** @return array<never> */
    public function extractActionItems(string $text, ?string $language = null): array
    {
        throw AiDisabledException::make();
    }

    /** @return array<never> */
    public function extractDecisions(string $text, ?string $language = null): array
    {
        throw AiDisabledException::make();
    }

    /** @return array<never> */
    public function extractRisks(string $text, ?string $language = null): array
    {
        throw AiDisabledException::make();
    }
}
