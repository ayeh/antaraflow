<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can register', function () {
    $response = $this->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('organizations.index'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
});

test('registration creates default organization', function () {
    $this->post(route('register'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user->organizations)->toHaveCount(1)
        ->and($user->organizations->first()->name)->toBe("Jane Doe's Workspace")
        ->and($user->current_organization_id)->not->toBeNull()
        ->and($user->organizations->first()->pivot->role)->toBe('owner');
});

test('user can login', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post(route('login'), [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('organizations.index'));
    $this->assertAuthenticatedAs($user);
});

test('user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

test('login updates last_login_at', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'last_login_at' => null,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login fails with incorrect credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post(route('login'), [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('registration requires valid data', function () {
    $response = $this->post(route('register'), []);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
});

test('registration requires unique email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('registration requires password confirmation', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('login page is accessible to guests', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
});

test('register page is accessible to guests', function () {
    $response = $this->get(route('register'));

    $response->assertSuccessful();
});

test('authenticated user is redirected from login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect();
});
