<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Account\Services\SubscriptionService;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = SubscriptionPlan::factory()->create([
        'name' => 'Free',
        'slug' => 'free-limit-test',
        'max_meetings_per_month' => 2,
        'max_audio_minutes_per_month' => 30,
        'max_storage_mb' => 100,
        'max_users' => 1,
        'features' => ['ai_summaries' => false, 'export' => false],
    ]);

    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    OrganizationSubscription::withoutGlobalScopes()->create([
        'organization_id' => $this->org->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('allows meeting creation within limit', function () {
    $this->actingAs($this->user)
        ->post(route('meetings.store'), [
            'title' => 'Test Meeting',
            'meeting_date' => now()->addDay()->format('Y-m-d'),
            'prepared_by' => $this->user->name,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('minutes_of_meetings', ['title' => 'Test Meeting']);
});

it('blocks meeting creation when limit reached', function () {
    $subscriptionService = app(SubscriptionService::class);
    $subscriptionService->incrementUsage($this->org, 'meetings', 2);

    $response = $this->actingAs($this->user)
        ->post(route('meetings.store'), [
            'title' => 'Over Limit Meeting',
            'meeting_date' => now()->addDay()->format('Y-m-d'),
            'prepared_by' => $this->user->name,
        ]);

    $response->assertRedirect(route('subscription.index'));
    $response->assertSessionHas('limit_exceeded');

    $this->assertDatabaseMissing('minutes_of_meetings', ['title' => 'Over Limit Meeting']);
});
