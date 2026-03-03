<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view users index', function () {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertStatus(200)
        ->assertSee('Users');
});

test('admin can search users', function () {
    User::factory()->create(['name' => 'John Doe']);
    User::factory()->create(['name' => 'Jane Smith']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['search' => 'John']))
        ->assertStatus(200)
        ->assertSee('John Doe')
        ->assertDontSee('Jane Smith');
});

test('admin can view user detail', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.show', $user))
        ->assertStatus(200)
        ->assertSee($user->name);
});

test('admin can suspend a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.suspend', $user))
        ->assertRedirect(route('admin.users.show', $user));

    expect($user->fresh()->trashed())->toBeTrue();
});

test('admin can unsuspend a user', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.unsuspend', $user))
        ->assertRedirect(route('admin.users.show', $user));

    expect($user->fresh()->trashed())->toBeFalse();
});

test('admin can impersonate a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
});

test('admin can export users csv', function () {
    User::factory()->count(3)->create();

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.export'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('suspended users appear in index with suspended badge', function () {
    $activeUser = User::factory()->create(['name' => 'Active User']);
    $suspendedUser = User::factory()->create(['name' => 'Suspended User']);
    $suspendedUser->delete();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertStatus(200)
        ->assertSee('Active User')
        ->assertSee('Suspended User')
        ->assertSee('Suspended');
});

test('unauthenticated user cannot access users', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('admin.login'));
});
