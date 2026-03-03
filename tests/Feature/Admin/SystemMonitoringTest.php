<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view system monitoring page', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.system.index'))
        ->assertStatus(200)
        ->assertSee('System Monitoring')
        ->assertSee(PHP_VERSION)
        ->assertSee(app()->version());
});

test('system page shows system info cards', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.system.index'))
        ->assertStatus(200)
        ->assertSee('PHP Version')
        ->assertSee('Laravel Version')
        ->assertSee('Database Driver')
        ->assertSee('Cache Driver')
        ->assertSee('Queue Driver');
});

test('system page shows disk usage', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.system.index'))
        ->assertStatus(200)
        ->assertSee('Disk Usage');
});

test('system page shows queue information', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.system.index'))
        ->assertStatus(200)
        ->assertSee('Queue Status');
});

test('unauthenticated cannot access system page', function () {
    $this->get(route('admin.system.index'))
        ->assertRedirect(route('admin.login'));
});
