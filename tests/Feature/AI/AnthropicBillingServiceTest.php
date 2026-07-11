<?php

declare(strict_types=1);

use App\Domain\AI\Services\AnthropicBillingService;
use Illuminate\Support\Facades\Http;

test('anthropic billing is not configured without an admin key', function () {
    config()->set('ai.anthropic_admin_key', null);

    expect(app(AnthropicBillingService::class)->isConfigured())->toBeFalse();
    expect(app(AnthropicBillingService::class)->monthCost())->toBeNull();
});

test('anthropic cost since sums amounts from the cost report api', function () {
    config()->set('ai.anthropic_admin_key', 'sk-ant-admin-test');

    // The Cost Report API returns `amount` in cents, so 150 + 225 = $3.75.
    Http::fake([
        'api.anthropic.com/v1/organizations/cost_report*' => Http::response([
            'data' => [
                ['results' => [['amount' => '150', 'currency' => 'USD']]],
                ['results' => [['amount' => '225', 'currency' => 'USD']]],
            ],
            'has_more' => false,
            'next_page' => null,
        ]),
    ]);

    expect(app(AnthropicBillingService::class)->costSince(now()->startOfMonth()))->toBe(3.75);
});

test('anthropic cost since returns null when the api fails', function () {
    config()->set('ai.anthropic_admin_key', 'sk-ant-admin-test');

    Http::fake([
        'api.anthropic.com/v1/organizations/cost_report*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    expect(app(AnthropicBillingService::class)->costSince(now()->startOfMonth()))->toBeNull();
});
