<?php

declare(strict_types=1);

use App\Domain\Account\Exceptions\LimitExceededException;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Account\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = SubscriptionPlan::factory()->create([
        'name' => 'Free',
        'slug' => 'free-test',
        'max_meetings_per_month' => 5,
        'max_audio_minutes_per_month' => 30,
        'max_storage_mb' => 100,
        'max_users' => 1,
        'features' => ['ai_summaries' => false, 'export' => false, 'api_access' => false],
    ]);

    $this->org = Organization::factory()->create();

    OrganizationSubscription::withoutGlobalScopes()->create([
        'organization_id' => $this->org->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $this->service = app(SubscriptionService::class);
});

it('returns true when usage is within limit', function () {
    expect($this->service->canPerform($this->org, 'meetings'))->toBeTrue();
});

it('throws LimitExceededException when limit exceeded', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->service->incrementUsage($this->org, 'meetings');
    }

    $this->service->checkLimit($this->org, 'meetings');
})->throws(LimitExceededException::class);

it('returns correct current usage', function () {
    $this->service->incrementUsage($this->org, 'meetings');
    $this->service->incrementUsage($this->org, 'meetings');

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(2);
});

it('returns null for unlimited plans', function () {
    $unlimitedPlan = SubscriptionPlan::factory()->create([
        'max_meetings_per_month' => -1,
    ]);

    OrganizationSubscription::withoutGlobalScopes()->where('organization_id', $this->org->id)->update([
        'subscription_plan_id' => $unlimitedPlan->id,
    ]);

    expect($this->service->getPlanLimit($this->org, 'meetings'))->toBeNull();
    expect($this->service->canPerform($this->org, 'meetings'))->toBeTrue();
});

it('checks feature flags correctly', function () {
    expect($this->service->isFeatureEnabled($this->org, 'ai_summaries'))->toBeFalse();
});

it('resets monthly usage', function () {
    $this->service->incrementUsage($this->org, 'meetings', 3);

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(3);

    $this->service->resetMonthlyUsage($this->org);

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(0);
});
