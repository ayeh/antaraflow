<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can view API keys index', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('api-keys.index'));

    $response->assertOk();
    $response->assertSee('API Keys');
});

test('index only shows keys from current organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    ApiKey::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Own Org Key',
    ]);

    ApiKey::factory()->create([
        'organization_id' => $otherOrg->id,
        'name' => 'Other Org Key',
    ]);

    $response = $this->actingAs($user)->get(route('api-keys.index'));

    $response->assertOk();
    $response->assertSee('Own Org Key');
    $response->assertDontSee('Other Org Key');
    $response->assertViewHas('apiKeys', fn ($keys) => $keys->count() === 1);
});

test('owner can create API key', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->post(route('api-keys.store'), [
        'name' => 'My Test Key',
        'permissions' => ['read', 'write'],
    ]);

    $response->assertRedirect(route('api-keys.index'));
    $response->assertSessionHas('api_key_created');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('api_keys', [
        'organization_id' => $org->id,
        'name' => 'My Test Key',
    ]);
});

test('created key starts with af_ prefix', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->post(route('api-keys.store'), [
        'name' => 'Prefix Test Key',
        'permissions' => ['read'],
    ]);

    $response->assertRedirect(route('api-keys.index'));

    $flashedKey = $response->getSession()->get('api_key_created');
    expect($flashedKey)->toStartWith('af_');
});

test('owner can revoke API key', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $apiKey = ApiKey::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($user)->delete(route('api-keys.destroy', $apiKey));

    $response->assertRedirect(route('api-keys.index'));
    $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id]);
});

test('member cannot manage API keys', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Member->value]);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)->get(route('api-keys.index'))->assertForbidden();

    $this->actingAs($user)->post(route('api-keys.store'), [
        'name' => 'Test',
        'permissions' => ['read'],
    ])->assertForbidden();
});

test('viewer cannot manage API keys', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Viewer->value]);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)->get(route('api-keys.index'))->assertForbidden();

    $this->actingAs($user)->post(route('api-keys.store'), [
        'name' => 'Test',
        'permissions' => ['read'],
    ])->assertForbidden();
});

test('cross-org: cannot revoke another org key', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $otherKey = ApiKey::factory()->create([
        'organization_id' => $otherOrg->id,
    ]);

    $response = $this->actingAs($user)->delete(route('api-keys.destroy', $otherKey));

    $response->assertNotFound();
    $this->assertDatabaseHas('api_keys', ['id' => $otherKey->id]);
});
