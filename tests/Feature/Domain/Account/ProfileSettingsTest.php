<?php

declare(strict_types=1);

use App\Domain\Account\Models\UserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can view profile settings page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('settings.profile'))->assertOk();
});

it('can update profile name and timezone', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.profile.update'), [
            'name' => 'New Name',
            'timezone' => 'Asia/Kuala_Lumpur',
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('New Name');
    expect(UserSettings::where('user_id', $user->id)->value('timezone'))->toBe('Asia/Kuala_Lumpur');
});
