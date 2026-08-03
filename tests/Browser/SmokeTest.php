<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the root page without javascript errors', function () {
    visit('/')->assertNoJavaScriptErrors();
});

/**
 * Sign in as a member of a fresh organization, under a chosen name.
 */
function memberNamed(string $name): User
{
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'current_organization_id' => $org->id,
        'name' => $name,
    ]);
    $org->members()->attach($user, ['role' => UserRole::Manager->value]);

    return $user;
}

it('survives an apostrophe in the signed-in user name on the meeting wizard', function () {
    /**
     * The reviewer's name was interpolated raw into the step-4 x-data
     * expression, so "Gina D'Amore" closed the JS string literal early and the
     * whole expression failed to parse. Everything it owned — action items,
     * comments, the AI extraction button, the stats row — silently stopped
     * working, for that user, on every meeting they opened. Apostrophes in
     * surnames are common; this was live.
     */
    $user = memberNamed("Gina D'Amore");
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $user->current_organization_id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    visit(route('meetings.show', $meeting).'?step=3')
        ->assertNoJavaScriptErrors();
});
