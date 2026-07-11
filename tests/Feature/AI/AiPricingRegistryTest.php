<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\AI\Models\AiModelPrice;
use App\Domain\AI\Services\AiPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function addPrice(array $attributes): void
{
    AiModelPrice::query()->create($attributes);
    AiPricingService::flushCache();
}

test('built-in prices are seeded into the registry by the migration', function () {
    expect(AiModelPrice::query()->where('pattern', 'gpt-5.4-mini')->exists())->toBeTrue();

    // Seeded rate still resolves: $0.75 input + $4.50 output per 1M
    expect(app(AiPricingService::class)->chatCost('gpt-5.4-mini', 1_000_000, 1_000_000))->toBe(5.25);
});

test('a higher-priority custom exact price overrides the built-in', function () {
    addPrice([
        'provider' => 'openai', 'pattern' => 'gpt-5.4-mini', 'is_regex' => false,
        'input_per_mtok' => 1.0, 'output_per_mtok' => 2.0, 'priority' => 500,
    ]);

    expect(app(AiPricingService::class)->chatCost('gpt-5.4-mini', 1_000_000, 1_000_000))->toBe(3.0);
});

test('a regex pattern prices otherwise-unknown models', function () {
    addPrice([
        'provider' => 'openai', 'pattern' => '^gpt-9', 'is_regex' => true,
        'input_per_mtok' => 10.0, 'output_per_mtok' => 20.0, 'priority' => 300,
    ]);

    expect(app(AiPricingService::class)->chatCost('gpt-9-turbo', 1_000_000, 0))->toBe(10.0);
});

test('cached prompt tokens are billed at the cached rate', function () {
    addPrice([
        'provider' => 'openai', 'pattern' => 'cache-model', 'is_regex' => false,
        'input_per_mtok' => 10.0, 'output_per_mtok' => 0.0, 'cached_input_per_mtok' => 1.0, 'priority' => 500,
    ]);

    // 1M prompt, 500k cached: 500k×$10 + 500k×$1 per 1M = 5.0 + 0.5
    expect(app(AiPricingService::class)->chatCost('cache-model', 1_000_000, 0, 500_000))->toBe(5.5);
});

test('exact match wins over regex regardless of priority', function () {
    addPrice(['provider' => 'x', 'pattern' => 'foo-model', 'is_regex' => false, 'input_per_mtok' => 1.0, 'output_per_mtok' => 0.0, 'priority' => 1]);
    addPrice(['provider' => 'x', 'pattern' => '^foo', 'is_regex' => true, 'input_per_mtok' => 99.0, 'output_per_mtok' => 0.0, 'priority' => 999]);

    expect(app(AiPricingService::class)->chatCost('foo-model', 1_000_000, 0))->toBe(1.0);
});

test('admin can add a model price and it flushes the cache', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.prices.store'), [
            'provider' => 'openai', 'pattern' => 'my-model', 'is_regex' => '1',
            'input_per_mtok' => 1.5, 'output_per_mtok' => 3.0, 'priority' => 200,
        ])
        ->assertRedirect(route('admin.ai.prices.index'));

    expect(AiModelPrice::query()->where('pattern', 'my-model')->where('is_regex', true)->exists())->toBeTrue();
});

test('admin can view the pricing registry page', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ai.prices.index'))
        ->assertStatus(200)
        ->assertSee('gpt-5.4-mini');
});
