<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->org->id,
        'timezone' => 'UTC',
        'language' => 'en',
    ]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('user can view profile edit page', function () {
    $response = $this->actingAs($this->user)->get(route('profile.edit'));

    $response->assertSuccessful();
    $response->assertSee($this->user->name);
    $response->assertSee($this->user->email);
});

test('user can update profile information', function () {
    $response = $this->actingAs($this->user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => $this->user->email,
        'timezone' => 'Asia/Kuala_Lumpur',
        'language' => 'ms',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Updated Name',
        'timezone' => 'Asia/Kuala_Lumpur',
        'language' => 'ms',
    ]);
});

test('user can update password', function () {
    $this->user->update(['password' => Hash::make('oldpassword123')]);

    $response = $this->actingAs($this->user)->put(route('profile.password'), [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
});

test('user cannot update password with wrong current password', function () {
    $this->user->update(['password' => Hash::make('correctpassword')]);

    $response = $this->actingAs($this->user)->put(route('profile.password'), [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('user can upload avatar', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->actingAs($this->user)->post(route('profile.avatar'), [
        'avatar' => $file,
    ]);

    $response->assertRedirect(route('profile.edit'));
    $this->assertNotNull($this->user->fresh()->avatar_path);
    Storage::disk('public')->assertExists($this->user->fresh()->avatar_path);
});

test('user can update preferences', function () {
    $response = $this->actingAs($this->user)->put(route('profile.preferences'), [
        'preferences' => [
            'theme' => 'dark',
            'default_meeting_duration' => 30,
            'notifications' => ['meeting_invite', 'action_item_assigned'],
        ],
    ]);

    $response->assertRedirect(route('profile.edit'));

    $user = $this->user->fresh();
    expect($user->preferences['theme'])->toBe('dark');
    expect($user->preferences['default_meeting_duration'])->toBe(30);
    expect($user->preferences['notifications'])->toContain('meeting_invite', 'action_item_assigned');
});

test('profile update validates required fields', function () {
    $response = $this->actingAs($this->user)->put(route('profile.update'), [
        'name' => '',
        'email' => '',
        'timezone' => '',
        'language' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'timezone', 'language']);
});
