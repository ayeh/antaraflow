<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin login page renders', function () {
    $response = $this->get(route('admin.login'));
    $response->assertStatus(200);
    $response->assertSee('Super Admin Panel');
});

test('admin can login with valid credentials', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(route('admin.login.attempt'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('admin cannot login with invalid credentials', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(route('admin.login.attempt'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect();
    $this->assertGuest('admin');
});

test('admin can logout', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});

test('unauthenticated user is redirected to admin login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

test('regular user cannot access admin dashboard', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});
