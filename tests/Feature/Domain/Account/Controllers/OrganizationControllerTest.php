<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can create organization', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('organizations.store'), [
        'name' => 'New Org',
        'slug' => 'new-org',
        'description' => 'A test organization',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('organizations', ['name' => 'New Org', 'slug' => 'new-org']);
});

test('guest cannot access organizations', function () {
    $response = $this->get(route('organizations.index'));

    $response->assertRedirect(route('login'));
});

test('user can view their organizations', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('organizations.index'));

    $response->assertSuccessful();
    $response->assertSee($org->name);
});

test('user can view organization they belong to', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Member->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('organizations.show', $org));

    $response->assertSuccessful();
});

test('owner can update organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->put(route('organizations.update', $org), [
        'name' => 'Updated Org',
        'slug' => $org->slug,
    ]);

    $response->assertRedirect();
    expect($org->fresh()->name)->toBe('Updated Org');
});

test('owner can delete organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->delete(route('organizations.destroy', $org));

    $response->assertRedirect(route('organizations.index'));
    expect(Organization::find($org->id))->toBeNull();
});

test('organization creation requires name and slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('organizations.store'), []);

    $response->assertSessionHasErrors(['name', 'slug']);
});

test('organization slug must be unique', function () {
    $user = User::factory()->create();
    Organization::factory()->create(['slug' => 'taken-slug']);

    $response = $this->actingAs($user)->post(route('organizations.store'), [
        'name' => 'New Org',
        'slug' => 'taken-slug',
    ]);

    $response->assertSessionHasErrors(['slug']);
});
