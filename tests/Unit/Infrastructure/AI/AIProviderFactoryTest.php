<?php

declare(strict_types=1);

use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Providers\AnthropicProvider;
use App\Infrastructure\AI\Providers\GoogleProvider;
use App\Infrastructure\AI\Providers\OllamaProvider;
use App\Infrastructure\AI\Providers\OpenAIProvider;

test('factory creates openai provider', function () {
    $provider = AIProviderFactory::make('openai', ['api_key' => 'test-key', 'model' => 'gpt-4o']);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
    expect($provider)->toBeInstanceOf(OpenAIProvider::class);
});

test('factory creates anthropic provider', function () {
    $provider = AIProviderFactory::make('anthropic', ['api_key' => 'test-key']);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
    expect($provider)->toBeInstanceOf(AnthropicProvider::class);
});

test('factory creates google provider', function () {
    $provider = AIProviderFactory::make('google', ['api_key' => 'test-key']);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
    expect($provider)->toBeInstanceOf(GoogleProvider::class);
});

test('factory creates ollama provider', function () {
    $provider = AIProviderFactory::make('ollama', ['base_url' => 'http://localhost:11434']);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
    expect($provider)->toBeInstanceOf(OllamaProvider::class);
});

test('factory throws exception for unknown provider', function () {
    AIProviderFactory::make('unknown', []);
})->throws(InvalidArgumentException::class, 'Unknown AI provider: unknown');

test('stub providers throw runtime exception', function (string $providerName, array $config) {
    $provider = AIProviderFactory::make($providerName, $config);

    expect(fn () => $provider->chat('test'))->toThrow(RuntimeException::class);
    expect(fn () => $provider->summarize('test'))->toThrow(RuntimeException::class);
    expect(fn () => $provider->extractActionItems('test'))->toThrow(RuntimeException::class);
    expect(fn () => $provider->extractDecisions('test'))->toThrow(RuntimeException::class);
})->with([
    'openai' => ['openai', ['api_key' => 'test-key']],
    'anthropic' => ['anthropic', ['api_key' => 'test-key']],
    'google' => ['google', ['api_key' => 'test-key']],
    'ollama' => ['ollama', ['base_url' => 'http://localhost:11434']],
]);
