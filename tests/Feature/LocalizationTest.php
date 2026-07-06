<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UserSettings;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function memberUser(): User
{
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    return $user;
}

it('defaults to English for a fresh guest session', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Login')
        ->assertSee('or continue with');

    expect(app()->getLocale())->toBe('en');
});

it('switches the guest session locale to Bahasa Melayu', function () {
    $this->get(route('locale.switch', 'ms'))->assertRedirect();

    expect(session('locale'))->toBe('ms');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Log Masuk')
        ->assertSee('atau teruskan dengan');
});

it('rejects an unsupported locale', function () {
    $this->get(route('locale.switch', 'fr'))->assertNotFound();
});

it('applies an authenticated user’s saved locale and renders translated UI', function () {
    $user = memberUser();
    UserSettings::create(['user_id' => $user->id, 'locale' => 'ms']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Papan Pemuka');

    expect(app()->getLocale())->toBe('ms');
});

it('persists the chosen locale to the user’s settings when switching', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('locale.switch', 'ms'))->assertRedirect();

    expect(UserSettings::where('user_id', $user->id)->value('locale'))->toBe('ms')
        ->and(session('locale'))->toBe('ms');
});

it('an explicit session choice takes precedence over the saved preference', function () {
    $user = memberUser();
    UserSettings::create(['user_id' => $user->id, 'locale' => 'ms']);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');

    expect(app()->getLocale())->toBe('en');
});
