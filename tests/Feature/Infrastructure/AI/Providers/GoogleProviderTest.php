<?php

declare(strict_types=1);

use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\Providers\GoogleProvider;
use Illuminate\Support\Facades\Http;

test('google provider can chat', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Test response']]]]],
        ]),
    ]);

    $provider = new GoogleProvider(apiKey: 'test-key');
    $result = $provider->chat('Hello');

    expect($result)->toBe('Test response');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com')
        && str_contains($request->url(), 'key=test-key')
    );
});

test('google provider can summarize', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'summary' => 'Google summary',
                'key_points' => '- Point 1',
                'confidence_score' => 0.88,
            ])]]]]],
        ]),
    ]);

    $provider = new GoogleProvider(apiKey: 'test-key');
    $result = $provider->summarize('Meeting transcript...');

    expect($result)->toBeInstanceOf(MeetingSummary::class)
        ->and($result->summary)->toBe('Google summary');
});

test('google provider uses specified model in url', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Response']]]]],
        ]),
    ]);

    $provider = new GoogleProvider(apiKey: 'test-key', model: 'gemini-pro');
    $provider->chat('Hello');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-pro'));
});
