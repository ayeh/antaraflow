<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomGuestAccess;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Quarterly Board Review',
    ]);
});

/** @param  array<string, mixed>  $overrides */
function guestLink(array $overrides = []): MomGuestAccess
{
    return MomGuestAccess::factory()->forMeeting(test()->meeting)->create($overrides);
}

function badgeSaysShared(): bool
{
    $meetings = test()->actingAs(test()->user)
        ->get(route('meetings.index'))
        ->assertSuccessful()
        ->viewData('meetings');

    return (bool) $meetings->firstWhere('id', test()->meeting->id)->is_shared_with_guests;
}

test('a meeting with no guest link is not marked as shared', function (): void {
    expect(badgeSaysShared())->toBeFalse();
});

test('a meeting with an active guest link is marked as shared', function (): void {
    guestLink();

    expect(badgeSaysShared())->toBeTrue();
});

test('a revoked guest link does not mark the meeting as shared', function (): void {
    guestLink(['is_active' => false]);

    expect(badgeSaysShared())->toBeFalse();
});

test('an expired guest link does not mark the meeting as shared', function (): void {
    guestLink(['expires_at' => now()->subDay()]);

    expect(badgeSaysShared())->toBeFalse();
});

test('a guest link with a future expiry still marks the meeting as shared', function (): void {
    guestLink(['expires_at' => now()->addWeek()]);

    expect(badgeSaysShared())->toBeTrue();
});

test('the badge never disagrees with what the public guest route accepts', function (): void {
    $cases = [
        'active, no expiry' => [[], true],
        'active, future expiry' => [['expires_at' => now()->addWeek()], true],
        'revoked' => [['is_active' => false], false],
        'expired' => [['expires_at' => now()->subDay()], false],
    ];

    foreach ($cases as $name => [$overrides, $expected]) {
        MomGuestAccess::withoutGlobalScopes()
            ->where('minutes_of_meeting_id', $this->meeting->id)
            ->delete();

        $link = guestLink($overrides);
        $routeOpens = $this->get(route('guest.mom', $link->token))->getStatusCode() === 200;

        expect(badgeSaysShared())->toBe($expected, "badge for: {$name}")
            ->and($routeOpens)->toBe($expected, "guest route for: {$name}");
    }
});

test('the shared badge is rendered on the meetings index', function (): void {
    guestLink();

    $this->actingAs($this->user)
        ->get(route('meetings.index'))
        ->assertSuccessful()
        ->assertSee('Shared');
});

test('the badge costs one query for the page, not one per meeting', function (): void {
    MinutesOfMeeting::factory()->count(5)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    guestLink();

    DB::enableQueryLog();
    $this->actingAs($this->user)->get(route('meetings.index'))->assertSuccessful();
    $guestQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $q): bool => str_contains($q, 'mom_guest_accesses'));
    DB::disableQueryLog();

    expect($guestQueries)->toHaveCount(1);
});
