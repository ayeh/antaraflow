<?php

declare(strict_types=1);

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can view AI provider configs index', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('ai-provider-configs.index'));

    $response->assertOk();
    $response->assertSee('AI Provider Configurations');
});

test('admin cannot view AI provider configs index', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Admin->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('ai-provider-configs.index'));

    $response->assertForbidden();
});

test('member cannot view AI provider configs', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Member->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('ai-provider-configs.index'));

    $response->assertForbidden();
});

test('viewer cannot view AI provider configs', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Viewer->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('ai-provider-configs.index'));

    $response->assertForbidden();
});

test('owner can create AI provider config', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->post(route('ai-provider-configs.store'), [
        'provider' => 'openai',
        'display_name' => 'My OpenAI Config',
        'api_key' => 'sk-test-key-12345',
        'model' => 'gpt-4o',
        'base_url' => null,
        'is_active' => true,
        'is_default' => false,
    ]);

    $response->assertRedirect(route('ai-provider-configs.index'));
    $this->assertDatabaseHas('ai_provider_configs', [
        'organization_id' => $org->id,
        'provider' => 'openai',
        'display_name' => 'My OpenAI Config',
        'model' => 'gpt-4o',
    ]);
});

test('creating default config clears other defaults', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $firstConfig = AiProviderConfig::factory()->create([
        'organization_id' => $org->id,
        'is_default' => true,
    ]);

    $this->actingAs($user)->post(route('ai-provider-configs.store'), [
        'provider' => 'anthropic',
        'display_name' => 'New Default',
        'api_key' => 'sk-ant-test-key',
        'model' => 'claude-3-5-sonnet-20241022',
        'is_active' => true,
        'is_default' => true,
    ]);

    expect($firstConfig->fresh()->is_default)->toBeFalse();
    $this->assertDatabaseHas('ai_provider_configs', [
        'display_name' => 'New Default',
        'is_default' => true,
    ]);
});

test('owner can update AI provider config', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $config = AiProviderConfig::factory()->create([
        'organization_id' => $org->id,
        'provider' => 'openai',
        'display_name' => 'Original Name',
        'model' => 'gpt-4',
    ]);

    $response = $this->actingAs($user)->put(route('ai-provider-configs.update', $config), [
        'provider' => 'openai',
        'display_name' => 'Updated Name',
        'model' => 'gpt-4o',
        'is_active' => true,
        'is_default' => false,
    ]);

    $response->assertRedirect(route('ai-provider-configs.index'));
    $this->assertDatabaseHas('ai_provider_configs', [
        'id' => $config->id,
        'display_name' => 'Updated Name',
        'model' => 'gpt-4o',
    ]);
});

test('owner can delete AI provider config', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $config = AiProviderConfig::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($user)->delete(route('ai-provider-configs.destroy', $config));

    $response->assertRedirect(route('ai-provider-configs.index'));
    $this->assertDatabaseMissing('ai_provider_configs', ['id' => $config->id]);
});

test('cross-org: cannot access another org config', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $org1->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org1->id]);

    $org2Config = AiProviderConfig::factory()->create([
        'organization_id' => $org2->id,
    ]);

    $response = $this->actingAs($user)->get(route('ai-provider-configs.edit', $org2Config));

    // BelongsToOrganization global scope causes route model binding to 404
    // before the explicit 403 check is reached
    $response->assertStatus(404);
});
