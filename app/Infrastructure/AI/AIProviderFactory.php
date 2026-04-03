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
    /** @var list<string> Allowed base URL hosts for AI providers */
    private const array ALLOWED_HOSTS = [
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com',
        'localhost',
        '127.0.0.1',
    ];

    /** @param  array<string, mixed>  $config */
    public static function make(string $provider, array $config): AIProviderInterface
    {
        if (isset($config['base_url'])) {
            self::validateBaseUrl($config['base_url']);
        }

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

    private static function validateBaseUrl(string $url): void
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Block private/internal IP ranges and cloud metadata
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                && ! in_array($ip, ['127.0.0.1', '::1'], true)
            ) {
                throw new InvalidArgumentException('AI provider base URL cannot point to private/internal IP addresses.');
            }
        }

        // Check against allowlist (skip for custom Ollama instances on localhost)
        $allowedHosts = array_merge(
            self::ALLOWED_HOSTS,
            array_filter(explode(',', config('ai.allowed_base_url_hosts', '')))
        );

        if (! in_array($host, $allowedHosts, true)) {
            throw new InvalidArgumentException("AI provider base URL host '{$host}' is not in the allowed hosts list.");
        }
    }
}
