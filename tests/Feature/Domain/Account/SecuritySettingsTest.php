<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('can view security settings page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('settings.security'))->assertOk();
});

it('can change password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->put(route('settings.security.password'), [
            'current_password' => 'old-password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])
        ->assertRedirect();

    expect(Hash::check('new-secure-password-123', $user->fresh()->password))->toBeTrue();
});
