<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UserSettings;
use App\Domain\Meeting\Models\MinutesOfMeeting;
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

it('translates the recorder button labels on the Malay UI', function () {
    /**
     * The labels used to be English literals inside an Alpine expression, so
     * they survived the locale switch untouched while their siblings did not.
     * Assert the rendered markup, because that is where the bug lived: the
     * translation existing in lang/ms.json proves nothing about what the
     * button says.
     */
    $user = memberUser();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $user->current_organization_id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['locale' => 'ms'])
        ->get(route('meetings.show', $meeting).'?step=3')
        ->assertOk();

    $response->assertSee("'Rakam'", false)
        ->assertSee("'Mulakan Rakaman'", false)
        ->assertDontSee("'Record'", false)
        ->assertDontSee("'Start Recording'", false);
});

it('escapes a translated label so an apostrophe cannot break the Alpine expression', function () {
    /**
     * The labels sit inside a JS expression inside a double-quoted attribute.
     * A translation containing an apostrophe would end the JS string literal
     * early and take the whole expression — every binding in it — down with
     * it. No shipped Malay string has one today; one added tomorrow would.
     * Force the case rather than wait for it.
     */
    $user = memberUser();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $user->current_organization_id,
        'created_by' => $user->id,
    ]);

    app('translator')->addLines(['*.Record' => "Rakam'kan"], 'ms');

    $this->actingAs($user)
        ->withSession(['locale' => 'ms'])
        ->get(route('meetings.show', $meeting).'?step=3')
        ->assertOk()
        ->assertSee('Rakam\u0027kan', false)
        ->assertDontSee("Rakam'kan", false);
});
