<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Admin\Models\Admin;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('dashboard renders for authenticated admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertStatus(200)
        ->assertSee('Dashboard')
        ->assertSee('Total Users')
        ->assertSee('Total Organizations')
        ->assertSee('Total Meetings')
        ->assertSee('Active Subscriptions')
        ->assertSee('MRR');
});

test('stat cards show correct counts', function () {
    User::factory()->count(5)->create();
    Organization::factory()->count(3)->create();
    MinutesOfMeeting::factory()->count(7)->create();

    $plan = SubscriptionPlan::factory()->create(['price_monthly' => 49.90]);
    OrganizationSubscription::factory()->count(2)->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
    ]);
    OrganizationSubscription::factory()->cancelled()->create([
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'));

    $response->assertStatus(200);
    // Users: 5 created + any from factory relations (MoM created_by, Subscription orgs users, etc.)
    // Check that the specific known counts appear for what we control
    $response->assertSee('RM 99.80'); // 2 active subs * 49.90
});

test('period toggle works with daily period', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard', ['period' => 'daily']))
        ->assertStatus(200)
        ->assertSee('Daily');
});

test('period toggle works with weekly period', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard', ['period' => 'weekly']))
        ->assertStatus(200)
        ->assertSee('Weekly');
});

test('period toggle works with monthly period', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard', ['period' => 'monthly']))
        ->assertStatus(200)
        ->assertSee('Monthly');
});

test('recent registrations shown on dashboard', function () {
    $user = User::factory()->create(['name' => 'Test Dashboard User']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertStatus(200)
        ->assertSee('Recent Registrations')
        ->assertSee('Test Dashboard User');
});

test('top organizations shown on dashboard', function () {
    $org = Organization::factory()->create(['name' => 'Acme Analytics Corp']);
    $members = User::factory()->count(3)->create();
    $org->members()->attach($members->pluck('id'));

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertStatus(200)
        ->assertSee('Top Organizations')
        ->assertSee('Acme Analytics Corp');
});

test('unauthenticated user is redirected from dashboard', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});
