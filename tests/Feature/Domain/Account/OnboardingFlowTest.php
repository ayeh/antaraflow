<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['onboarding_completed_at' => null]);
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

it('shows step 1 form', function () {
    $this->actingAs($this->user)
        ->get(route('onboarding.step', ['step' => 1]))
        ->assertOk()
        ->assertSee('Complete Your Profile');
});

it('processes step 1 and advances to step 2', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 2]));

    expect($this->user->fresh()->name)->toBe('Updated Name');
});

it('processes step 2 and advances to step 3', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.update', ['step' => 2]), [
            'name' => $this->org->name,
            'timezone' => 'Asia/Kuala_Lumpur',
            'language' => 'en',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 3]));
});

it('skips onboarding and marks complete', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.skip'))
        ->assertRedirect(route('dashboard'));

    expect($this->user->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('redirects to onboarding after registration', function () {
    $response = $this->post(route('register'), [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('onboarding.step', ['step' => 1]));
});
