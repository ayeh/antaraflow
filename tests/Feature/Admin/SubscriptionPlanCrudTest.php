<?php

declare(strict_types=1);

use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view plans index', function () {
    SubscriptionPlan::factory()->count(3)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.plans.index'))
        ->assertStatus(200)
        ->assertSee('Subscription Plans');
});

test('admin can view create plan form', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.plans.create'))
        ->assertStatus(200)
        ->assertSee('Create Plan');
});

test('admin can create a plan', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.plans.store'), [
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'description' => 'A test plan',
            'price_monthly' => 29.99,
            'price_yearly' => 299.99,
            'features' => ['export' => true, 'ai_summaries' => false],
            'max_users' => 10,
            'max_meetings_per_month' => 50,
            'max_audio_minutes_per_month' => 600,
            'max_storage_mb' => 5120,
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.plans.index'));

    $this->assertDatabaseHas('subscription_plans', [
        'name' => 'Test Plan',
        'slug' => 'test-plan',
    ]);
});

test('admin cannot create plan with duplicate slug', function () {
    SubscriptionPlan::factory()->create(['slug' => 'existing-slug']);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.plans.store'), [
            'name' => 'Another Plan',
            'slug' => 'existing-slug',
            'price_monthly' => 10,
            'price_yearly' => 100,
            'features' => ['export' => true],
            'max_users' => 5,
            'max_meetings_per_month' => 20,
            'max_audio_minutes_per_month' => 300,
            'max_storage_mb' => 1000,
        ])
        ->assertSessionHasErrors('slug');
});

test('admin can view edit plan form', function () {
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.plans.edit', $plan))
        ->assertStatus(200)
        ->assertSee($plan->name);
});

test('admin can update a plan', function () {
    $plan = SubscriptionPlan::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.plans.update', $plan), [
            'name' => 'New Name',
            'slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'features' => $plan->features ?? ['export' => true],
            'max_users' => $plan->max_users,
            'max_meetings_per_month' => $plan->max_meetings_per_month,
            'max_audio_minutes_per_month' => $plan->max_audio_minutes_per_month,
            'max_storage_mb' => $plan->max_storage_mb,
        ])
        ->assertRedirect(route('admin.plans.index'));

    expect($plan->fresh()->name)->toBe('New Name');
});

test('admin can update plan slug to same value', function () {
    $plan = SubscriptionPlan::factory()->create(['slug' => 'my-plan']);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'slug' => 'my-plan',
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'features' => $plan->features ?? ['export' => true],
            'max_users' => $plan->max_users,
            'max_meetings_per_month' => $plan->max_meetings_per_month,
            'max_audio_minutes_per_month' => $plan->max_audio_minutes_per_month,
            'max_storage_mb' => $plan->max_storage_mb,
        ])
        ->assertRedirect(route('admin.plans.index'));
});

test('admin cannot delete plan with active subscribers', function () {
    $plan = SubscriptionPlan::factory()->create();

    OrganizationSubscription::factory()->create([
        'subscription_plan_id' => $plan->id,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect(route('admin.plans.index'))
        ->assertSessionHas('error', 'Cannot delete a plan with active subscribers.');

    $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
});

test('admin can delete plan with no subscribers', function () {
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect(route('admin.plans.index'))
        ->assertSessionHas('success', 'Plan deleted successfully.');

    $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
});

test('plan creation requires name', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.plans.store'), [
            'slug' => 'test',
            'price_monthly' => 10,
            'price_yearly' => 100,
            'features' => ['export' => true],
            'max_users' => 5,
            'max_meetings_per_month' => 20,
            'max_audio_minutes_per_month' => 300,
            'max_storage_mb' => 1000,
        ])
        ->assertSessionHasErrors('name');
});

test('unauthenticated user cannot access plans', function () {
    $this->get(route('admin.plans.index'))
        ->assertRedirect(route('admin.login'));
});
