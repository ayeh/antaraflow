<?php

declare(strict_types=1);

use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Http;

test('openai provider can chat', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Test response']]],
            'usage' => ['total_tokens' => 150],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $result = $provider->chat('Hello');

    expect($result)->toBe('Test response');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
    );
});

test('openai provider sends system message in context', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Response']]],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $provider->chat('Hello', ['system' => 'You are helpful.']);

    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'];

        return $messages[0]['role'] === 'system'
            && $messages[0]['content'] === 'You are helpful.'
            && $messages[1]['role'] === 'user';
    });
});

test('openai provider can summarize', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 'Meeting summary',
                'key_points' => '- Point 1\n- Point 2',
                'confidence_score' => 0.95,
            ])]]],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $result = $provider->summarize('Meeting transcript...');

    expect($result)->toBeInstanceOf(MeetingSummary::class)
        ->and($result->summary)->toBe('Meeting summary')
        ->and($result->confidenceScore)->toBe(0.95);
});

test('openai provider can extract action items', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                ['title' => 'Review PR', 'assignee' => 'John', 'priority' => 'high'],
                ['title' => 'Update docs', 'priority' => 'low'],
            ])]]],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $result = $provider->extractActionItems('Meeting transcript...');

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ExtractedActionItem::class)
        ->and($result[0]->title)->toBe('Review PR')
        ->and($result[0]->assignee)->toBe('John')
        ->and($result[0]->priority)->toBe('high')
        ->and($result[1]->title)->toBe('Update docs');
});

test('openai provider can extract decisions', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                ['decision' => 'Use Laravel', 'context' => 'Framework choice', 'made_by' => 'Team'],
            ])]]],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $result = $provider->extractDecisions('Meeting transcript...');

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(ExtractedDecision::class)
        ->and($result[0]->decision)->toBe('Use Laravel')
        ->and($result[0]->context)->toBe('Framework choice')
        ->and($result[0]->madeBy)->toBe('Team');
});

test('openai provider uses specified model', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Response']]],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key', model: 'gpt-4-turbo');
    $provider->chat('Hello');

    Http::assertSent(fn ($request) => $request->data()['model'] === 'gpt-4-turbo');
});
