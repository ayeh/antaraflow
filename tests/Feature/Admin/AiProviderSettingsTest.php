<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\AiControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can switch the active provider and override a model', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.ai.update-provider'), [
            'active_provider' => 'anthropic',
            'models' => ['anthropic' => 'claude-sonnet-4-6'],
            'keys' => [],
        ])
        ->assertRedirect(route('admin.ai.index'));

    $control = app(AiControlService::class);

    expect($control->activeProviderOverride())->toBe('anthropic')
        ->and($control->modelOverrides())->toMatchArray(['anthropic' => 'claude-sonnet-4-6']);
});

test('api keys are stored encrypted and applied to config at runtime', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.ai.update-provider'), [
            'active_provider' => 'openai',
            'keys' => ['openai' => 'sk-secret-value-9999'],
        ])
        ->assertRedirect(route('admin.ai.index'));

    $raw = PlatformSetting::query()->where('key', 'ai_provider_keys')->value('value');
    expect($raw)->not->toContain('sk-secret-value-9999');

    config(['ai.providers.openai.api_key' => 'env-fallback']);
    app(AiControlService::class)->applyRuntimeOverrides();

    expect(config('ai.providers.openai.api_key'))->toBe('sk-secret-value-9999');
});

test('a blank key field keeps the existing stored key', function () {
    $control = app(AiControlService::class);
    $control->setApiKey('openai', 'sk-original-key');

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.ai.update-provider'), [
            'active_provider' => 'openai',
            'keys' => ['openai' => ''],
        ])
        ->assertRedirect(route('admin.ai.index'));

    expect($control->hasKeyOverride('openai'))->toBeTrue();

    config(['ai.providers.openai.api_key' => 'env-fallback']);
    $control->applyRuntimeOverrides();
    expect(config('ai.providers.openai.api_key'))->toBe('sk-original-key');
});

test('clearing a key reverts to the env value', function () {
    $control = app(AiControlService::class);
    $control->setApiKey('google', 'sk-google-db-key');

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.ai.update-provider'), [
            'active_provider' => 'openai',
            'clear_keys' => ['google' => '1'],
        ])
        ->assertRedirect(route('admin.ai.index'));

    expect($control->hasKeyOverride('google'))->toBeFalse();
});

test('an invalid provider is rejected', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.ai.update-provider'), [
            'active_provider' => 'not-a-provider',
        ])
        ->assertSessionHasErrors('active_provider');
});
