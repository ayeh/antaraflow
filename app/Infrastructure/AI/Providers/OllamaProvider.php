<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use RuntimeException;

class OllamaProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $model = 'llama3.2',
    ) {}

    /** {@inheritDoc} */
    public function chat(string $prompt, array $context = []): string
    {
        throw new RuntimeException('Ollama provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function summarize(string $text): MeetingSummary
    {
        throw new RuntimeException('Ollama provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function extractActionItems(string $text): array
    {
        throw new RuntimeException('Ollama provider not yet implemented.');
    }

    /** {@inheritDoc} */
    public function extractDecisions(string $text): array
    {
        throw new RuntimeException('Ollama provider not yet implemented.');
    }
}
