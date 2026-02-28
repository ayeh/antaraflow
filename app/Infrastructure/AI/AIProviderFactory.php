<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Providers\AnthropicProvider;
use App\Infrastructure\AI\Providers\GoogleProvider;
use App\Infrastructure\AI\Providers\OllamaProvider;
use App\Infrastructure\AI\Providers\OpenAIProvider;
use InvalidArgumentException;

class AIProviderFactory
{
    /** @param  array<string, mixed>  $config */
    public static function make(string $provider, array $config): AIProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAIProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'gpt-4o',
            ),
            'anthropic' => new AnthropicProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'claude-sonnet-4-20250514',
            ),
            'google' => new GoogleProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'gemini-2.0-flash',
            ),
            'ollama' => new OllamaProvider(
                baseUrl: $config['base_url'] ?? 'http://localhost:11434',
                model: $config['model'] ?? 'llama3.2',
            ),
            default => throw new InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
