<?php

declare(strict_types=1);

use App\Domain\AI\Models\AiUsageLog;
use App\Infrastructure\AI\Providers\AnthropicProvider;
use App\Infrastructure\AI\Providers\GoogleProvider;
use App\Infrastructure\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('anthropic provider records token usage and cost', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'hi']],
            'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000],
        ]),
    ]);

    (new AnthropicProvider('key', 'claude-sonnet-4-20250514'))->chat('hello');

    $log = AiUsageLog::query()->where('provider', 'anthropic')->firstOrFail();

    expect($log->prompt_tokens)->toBe(1_000_000);
    expect($log->completion_tokens)->toBe(1_000_000);
    // $3 input + $15 output per 1M
    expect((float) $log->cost)->toBe(18.0);
});

test('google provider records token usage from usageMetadata', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'hi']]]]],
            'usageMetadata' => ['promptTokenCount' => 1_000_000, 'candidatesTokenCount' => 1_000_000],
        ]),
    ]);

    (new GoogleProvider('key', 'gemini-2.0-flash'))->chat('hello');

    $log = AiUsageLog::query()->where('provider', 'google')->firstOrFail();

    expect($log->prompt_tokens)->toBe(1_000_000);
    expect($log->completion_tokens)->toBe(1_000_000);
    // $0.10 input + $0.40 output per 1M
    expect((float) $log->cost)->toBe(0.5);
});

test('openai provider records usage and operation label', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
    ]);

    (new OpenAIProvider('key', 'gpt-5.4-mini'))->chat('hello', ['operation' => 'summarize']);

    $log = AiUsageLog::query()->where('provider', 'openai')->firstOrFail();

    expect($log->operation)->toBe('summarize');
    expect($log->total_tokens)->toBe(150);
});
