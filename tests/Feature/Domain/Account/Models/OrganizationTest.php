<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('organization can be created with factory', function () {
    $org = Organization::factory()->create();

    expect($org)->toBeInstanceOf(Organization::class);
    expect($org->name)->toBeString();
    expect($org->slug)->toBeString();
});

test('organization has many users', function () {
    $org = Organization::factory()->create();
    User::factory()->create(['current_organization_id' => $org->id]);

    expect($org->users)->toHaveCount(1);
});

test('user belongs to current organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    expect($user->currentOrganization->id)->toBe($org->id);
});

test('user can belong to multiple organizations with roles', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $user->organizations()->attach($org1, ['role' => UserRole::Owner->value]);
    $user->organizations()->attach($org2, ['role' => UserRole::Member->value]);

    expect($user->organizations)->toHaveCount(2);
});

test('organization has members with roles', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $org->members()->attach($owner, ['role' => UserRole::Owner->value]);
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    expect($org->members)->toHaveCount(2);
    expect($org->members->first()->pivot->role)->toBe(UserRole::Owner->value);
});

test('organization uses soft deletes', function () {
    $org = Organization::factory()->create();
    $org->delete();

    expect(Organization::query()->count())->toBe(0);
    expect(Organization::withTrashed()->count())->toBe(1);
});

test('organization casts settings to array', function () {
    $org = Organization::factory()->create(['settings' => ['theme' => 'dark']]);
    $org->refresh();

    expect($org->settings)->toBeArray();
    expect($org->settings['theme'])->toBe('dark');
});
