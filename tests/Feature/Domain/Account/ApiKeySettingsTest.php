<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can view api key settings page', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user->id, ['role' => UserRole::Owner->value]);

    $this->actingAs($user)->get(route('settings.api-keys'))->assertOk();
});

it('can generate a new API key', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user->id, ['role' => UserRole::Owner->value]);

    $this->actingAs($user)
        ->post(route('settings.api-keys.store'), [
            'name' => 'My Key',
            'permissions' => ['read'],
        ])
        ->assertRedirect();

    expect(ApiKey::where('organization_id', $org->id)->count())->toBe(1);
});

it('can revoke an API key', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user->id, ['role' => UserRole::Owner->value]);

    $apiKey = ApiKey::factory()->create(['organization_id' => $org->id]);

    $this->actingAs($user)
        ->delete(route('settings.api-keys.destroy', $apiKey))
        ->assertRedirect();

    expect(ApiKey::find($apiKey->id))->toBeNull();
});
