<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Account\Services\OrganizationService;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrganizationService::class);
});

test('can create organization with owner', function () {
    $user = User::factory()->create();

    $organization = $this->service->createOrganization($user, 'Test Org');

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->name)->toBe('Test Org')
        ->and($organization->members)->toHaveCount(1)
        ->and($organization->members->first()->id)->toBe($user->id)
        ->and($organization->members->first()->pivot->role)->toBe(UserRole::Owner->value)
        ->and($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('creating organization assigns free subscription when plan exists', function () {
    SubscriptionPlan::factory()->create(['slug' => 'free', 'price_monthly' => 0]);

    $user = User::factory()->create();
    $organization = $this->service->createOrganization($user, 'Test Org');

    $subscription = OrganizationSubscription::withoutGlobalScopes()
        ->where('organization_id', $organization->id)
        ->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe('active');
});

test('can invite member to organization', function () {
    $owner = User::factory()->create();
    $organization = $this->service->createOrganization($owner, 'Test Org');

    $this->actingAs($owner);

    $newMember = User::factory()->create();
    $this->service->inviteMember($organization, $newMember, UserRole::Member);

    expect($organization->fresh()->members)->toHaveCount(2);

    $addedMember = $organization->members()->where('user_id', $newMember->id)->first();
    expect($addedMember->pivot->role)->toBe(UserRole::Member->value);
});

test('can remove member from organization', function () {
    $owner = User::factory()->create();
    $organization = $this->service->createOrganization($owner, 'Test Org');

    $this->actingAs($owner);

    $member = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->members()->attach($member, ['role' => UserRole::Member->value]);

    expect($organization->fresh()->members)->toHaveCount(2);

    $this->service->removeMember($organization, $member);

    expect($organization->fresh()->members)->toHaveCount(1);
});

test('removing member updates their current organization', function () {
    $owner = User::factory()->create();
    $organization = $this->service->createOrganization($owner, 'Test Org');

    $this->actingAs($owner);

    $member = User::factory()->create(['current_organization_id' => $organization->id]);
    $organization->members()->attach($member, ['role' => UserRole::Member->value]);

    $this->service->removeMember($organization, $member);

    expect($member->fresh()->current_organization_id)->not->toBe($organization->id);
});

test('can change member role', function () {
    $owner = User::factory()->create();
    $organization = $this->service->createOrganization($owner, 'Test Org');

    $this->actingAs($owner);

    $member = User::factory()->create();
    $organization->members()->attach($member, ['role' => UserRole::Member->value]);

    $this->service->changeRole($organization, $member, UserRole::Admin);

    $updatedMember = $organization->members()->where('user_id', $member->id)->first();
    expect($updatedMember->pivot->role)->toBe(UserRole::Admin->value);
});

test('can update organization settings', function () {
    $owner = User::factory()->create();
    $organization = $this->service->createOrganization($owner, 'Test Org');

    $this->actingAs($owner);

    $result = $this->service->updateSettings($organization, ['theme' => 'dark', 'notifications' => true]);

    expect($result->settings)->toBe(['theme' => 'dark', 'notifications' => true]);
});
