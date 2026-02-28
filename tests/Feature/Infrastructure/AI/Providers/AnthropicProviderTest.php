<?php

declare(strict_types=1);

use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\Providers\AnthropicProvider;
use Illuminate\Support\Facades\Http;

test('anthropic provider can chat', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Test response']],
        ]),
    ]);

    $provider = new AnthropicProvider(apiKey: 'test-key');
    $result = $provider->chat('Hello');

    expect($result)->toBe('Test response');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
    );
});

test('anthropic provider can summarize', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'summary' => 'Anthropic summary',
                'key_points' => '- Point 1',
                'confidence_score' => 0.9,
            ])]],
        ]),
    ]);

    $provider = new AnthropicProvider(apiKey: 'test-key');
    $result = $provider->summarize('Meeting transcript...');

    expect($result)->toBeInstanceOf(MeetingSummary::class)
        ->and($result->summary)->toBe('Anthropic summary');
});

test('anthropic provider sends system prompt separately', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Response']],
        ]),
    ]);

    $provider = new AnthropicProvider(apiKey: 'test-key');
    $provider->chat('Hello', ['system' => 'Be helpful']);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['system'] === 'Be helpful'
            && $data['messages'][0]['role'] === 'user';
    });
});
