<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Services\AiUsageContext;
use App\Infrastructure\AI\Providers\OpenAIProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('usage context attributes organization, user and feature', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    app(AiUsageContext::class)->set(organizationId: $org->id, userId: $user->id, feature: 'search');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'x']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    (new OpenAIProvider('key', 'gpt-5.4-mini'))->chat('hi');

    $log = AiUsageLog::query()->firstOrFail();

    expect($log->organization_id)->toBe($org->id);
    expect($log->user_id)->toBe($user->id);
    expect($log->feature)->toBe('search');
});

test('openai records cached tokens, duration and success status', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'prompt_tokens_details' => ['cached_tokens' => 40],
            ],
        ]),
    ]);

    (new OpenAIProvider('key', 'gpt-5.4-mini'))->chat('hi');

    $log = AiUsageLog::query()->firstOrFail();

    expect($log->cached_tokens)->toBe(40);
    expect($log->status)->toBe('success');
    expect($log->duration_ms)->not->toBeNull();
});

test('a failed provider call is recorded as an error', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    expect(fn () => (new OpenAIProvider('key', 'gpt-5.4-mini'))->chat('hi'))
        ->toThrow(RequestException::class);

    $log = AiUsageLog::query()->where('status', 'error')->firstOrFail();

    expect($log->provider)->toBe('openai');
    expect($log->total_tokens)->toBe(0);
});

test('usage context falls back to null when nothing is set and no auth', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]),
    ]);

    (new OpenAIProvider('key', 'gpt-5.4-mini'))->chat('hi');

    $log = AiUsageLog::query()->firstOrFail();

    expect($log->organization_id)->toBeNull();
    expect($log->feature)->toBeNull();
});
