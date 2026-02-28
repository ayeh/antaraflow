<?php

declare(strict_types=1);

use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;

test('ollama provider can chat', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'Test response'],
        ]),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->chat('Hello');

    expect($result)->toBe('Test response');

    Http::assertSent(fn ($request) => $request->url() === 'http://localhost:11434/api/chat'
        && $request->data()['stream'] === false
    );
});

test('ollama provider can summarize', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => json_encode([
                'summary' => 'Ollama summary',
                'key_points' => '- Point 1',
                'confidence_score' => 0.75,
            ])],
        ]),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->summarize('Meeting transcript...');

    expect($result)->toBeInstanceOf(MeetingSummary::class)
        ->and($result->summary)->toBe('Ollama summary');
});

test('ollama provider uses custom base url', function () {
    Http::fake([
        'custom-host:11434/*' => Http::response([
            'message' => ['content' => 'Response'],
        ]),
    ]);

    $provider = new OllamaProvider(baseUrl: 'http://custom-host:11434');
    $provider->chat('Hello');

    Http::assertSent(fn ($request) => $request->url() === 'http://custom-host:11434/api/chat');
});
