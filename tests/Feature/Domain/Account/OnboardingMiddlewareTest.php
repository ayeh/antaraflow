<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects new users to onboarding', function () {
    $user = User::factory()->create(['onboarding_completed_at' => null]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.step', ['step' => 1]));
});

it('allows access for onboarded users', function () {
    $user = User::factory()->create(['onboarding_completed_at' => now()]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows access to onboarding routes without redirect loop', function () {
    $user = User::factory()->create(['onboarding_completed_at' => null]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('onboarding.step', ['step' => 1]))
        ->assertOk();
});
