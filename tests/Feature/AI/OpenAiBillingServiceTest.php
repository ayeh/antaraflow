<?php

declare(strict_types=1);

use App\Domain\AI\Services\OpenAiBillingService;
use Illuminate\Support\Facades\Http;

test('billing is not configured without an admin key', function () {
    config()->set('ai.openai_admin_key', null);

    expect(app(OpenAiBillingService::class)->isConfigured())->toBeFalse();
    expect(app(OpenAiBillingService::class)->monthCost())->toBeNull();
});

test('cost since sums amounts from the costs api', function () {
    config()->set('ai.openai_admin_key', 'sk-admin-test');

    Http::fake([
        'api.openai.com/v1/organization/costs*' => Http::response([
            'data' => [
                ['results' => [['amount' => ['value' => 1.25, 'currency' => 'usd']]]],
                ['results' => [['amount' => ['value' => 2.75, 'currency' => 'usd']]]],
            ],
            'has_more' => false,
            'next_page' => null,
        ]),
    ]);

    $cost = app(OpenAiBillingService::class)->costSince(now()->startOfMonth());

    expect($cost)->toBe(4.0);
});

test('cost since returns null when the api fails', function () {
    config()->set('ai.openai_admin_key', 'sk-admin-test');

    Http::fake([
        'api.openai.com/v1/organization/costs*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    expect(app(OpenAiBillingService::class)->costSince(now()->startOfMonth()))->toBeNull();
});

test('cost is scoped to the project when OPENAI_PROJECT_ID is set', function () {
    config()->set('ai.openai_admin_key', 'sk-admin-test');
    config()->set('ai.openai_project_id', 'proj_antaranote');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [['results' => [['amount' => ['value' => 1.0, 'currency' => 'usd']]]]],
            'has_more' => false,
            'next_page' => null,
        ]),
    ]);

    expect(app(OpenAiBillingService::class)->projectId())->toBe('proj_antaranote');
    app(OpenAiBillingService::class)->costSince(now()->startOfMonth());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'proj_antaranote')
        && str_contains(rawurldecode($request->url()), 'project_ids[]'));
});
