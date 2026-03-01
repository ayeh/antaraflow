<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

it('renders the app shell with appState Alpine component', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('x-data="appState"', false)
        ->assertSee('commandPaletteOpen', false)
        ->assertSee('cycleTheme', false)
        ->assertSee('fabExpanded', false);
});

it('renders the icon rail', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('activeFlyout', false)
        ->assertSee('Home', false)
        ->assertSee('Meetings', false)
        ->assertSee('Settings', false);
});

it('renders the command palette overlay', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('commandQuery', false)
        ->assertSee('commandInput', false);
});

it('renders the FAB', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('fabExpanded', false)
        ->assertSee('meetings/create', false);
});

it('renders theme toggle controls', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('cycleTheme', false);
});
