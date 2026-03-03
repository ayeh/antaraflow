<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Admin\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view organizations index', function () {
    Organization::factory()->count(3)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizations.index'))
        ->assertStatus(200)
        ->assertSee('Organizations');
});

test('admin can search organizations', function () {
    Organization::factory()->create(['name' => 'Acme Corp']);
    Organization::factory()->create(['name' => 'Globex Inc']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizations.index', ['search' => 'Acme']))
        ->assertStatus(200)
        ->assertSee('Acme Corp')
        ->assertDontSee('Globex Inc');
});

test('admin can filter organizations by status', function () {
    Organization::factory()->create(['name' => 'Active Org', 'is_suspended' => false]);
    Organization::factory()->create(['name' => 'Suspended Org', 'is_suspended' => true, 'suspended_at' => now(), 'suspended_reason' => 'Test']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizations.index', ['status' => 'suspended']))
        ->assertStatus(200)
        ->assertSee('Suspended Org')
        ->assertDontSee('Active Org');
});

test('admin can view organization detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->members()->attach($user, ['role' => 'owner']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizations.show', $organization))
        ->assertStatus(200)
        ->assertSee($organization->name)
        ->assertSee($user->name);
});

test('admin can suspend an organization with reason', function () {
    $organization = Organization::factory()->create(['is_suspended' => false]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.organizations.suspend', $organization), [
            'reason' => 'Violation of terms of service',
        ])
        ->assertRedirect(route('admin.organizations.show', $organization));

    $organization->refresh();

    expect($organization->is_suspended)->toBeTrue()
        ->and($organization->suspended_reason)->toBe('Violation of terms of service')
        ->and($organization->suspended_at)->not->toBeNull();
});

test('suspend requires a reason', function () {
    $organization = Organization::factory()->create(['is_suspended' => false]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.organizations.suspend', $organization), [
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');
});

test('admin can unsuspend an organization', function () {
    $organization = Organization::factory()->create([
        'is_suspended' => true,
        'suspended_at' => now(),
        'suspended_reason' => 'Test reason',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.organizations.unsuspend', $organization))
        ->assertRedirect(route('admin.organizations.show', $organization));

    $organization->refresh();

    expect($organization->is_suspended)->toBeFalse()
        ->and($organization->suspended_at)->toBeNull()
        ->and($organization->suspended_reason)->toBeNull();
});

test('admin can change an organization plan', function () {
    $oldPlan = SubscriptionPlan::factory()->create(['name' => 'Starter']);
    $newPlan = SubscriptionPlan::factory()->create(['name' => 'Professional']);
    $organization = Organization::factory()->create();

    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'subscription_plan_id' => $oldPlan->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.organizations.change-plan', $organization), [
            'plan_id' => $newPlan->id,
        ])
        ->assertRedirect(route('admin.organizations.show', $organization));

    $activeSubscription = $organization->subscriptions()->where('status', 'active')->first();

    expect($activeSubscription->subscription_plan_id)->toBe($newPlan->id);
});

test('admin can assign a plan when organization has no active subscription', function () {
    $plan = SubscriptionPlan::factory()->create(['name' => 'Enterprise']);
    $organization = Organization::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.organizations.change-plan', $organization), [
            'plan_id' => $plan->id,
        ])
        ->assertRedirect(route('admin.organizations.show', $organization));

    $activeSubscription = $organization->subscriptions()->where('status', 'active')->first();

    expect($activeSubscription)->not->toBeNull()
        ->and($activeSubscription->subscription_plan_id)->toBe($plan->id);
});

test('unauthenticated user cannot access organizations', function () {
    $this->get(route('admin.organizations.index'))
        ->assertRedirect(route('admin.login'));
});
