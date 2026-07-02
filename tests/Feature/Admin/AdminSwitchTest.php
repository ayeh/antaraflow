<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a linked user can switch to the admin panel', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'web')
        ->post(route('switch-to-admin'))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
});

test('an unlinked user cannot switch to the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->post(route('switch-to-admin'))
        ->assertForbidden();

    $this->assertGuest('admin');
});

test('a linked admin can switch back to their user account', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->create(['user_id' => $user->id]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.switch-to-user'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
});

test('an unlinked admin cannot switch to a user account', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.switch-to-user'))
        ->assertForbidden();

    $this->assertGuest('web');
});

test('an impersonated session cannot switch to the admin panel even if the user is linked', function () {
    $linkedUser = User::factory()->create();
    Admin::factory()->create(['user_id' => $linkedUser->id]);

    $originalAdmin = Admin::factory()->create();

    $this->actingAs($linkedUser, 'web')
        ->withSession(['admin_impersonating' => $originalAdmin->id])
        ->post(route('switch-to-admin'))
        ->assertForbidden();

    $this->assertGuest('admin');
});
