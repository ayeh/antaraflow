<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
});

it('security settings route redirects to profile settings', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('settings.security'))->assertRedirect(route('settings.profile'));
});

it('can change password from profile settings', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPass1')]);

    $this->actingAs($user)
        ->put(route('settings.profile.password'), [
            'current_password' => 'OldPass1',
            'password' => 'NewPass1secure',
            'password_confirmation' => 'NewPass1secure',
        ])
        ->assertRedirect();

    expect(Hash::check('NewPass1secure', $user->fresh()->password))->toBeTrue();
});
