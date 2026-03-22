<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomGuestAccess;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

it('can create a guest access link', function (): void {
    $this->actingAs($this->user)
        ->post(route('meetings.guest-access.store', $this->meeting), ['label' => 'Client ABC'])
        ->assertRedirect();

    expect(MomGuestAccess::withoutGlobalScopes()->where('minutes_of_meeting_id', $this->meeting->id)->count())->toBe(1);
});

it('guest can view meeting via token', function (): void {
    MomGuestAccess::withoutGlobalScopes()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'organization_id' => $this->org->id,
        'token' => 'test-token-abc',
        'is_active' => true,
    ]);

    $this->get(route('guest.mom', 'test-token-abc'))
        ->assertOk()
        ->assertViewIs('meetings.guest');
});

it('expired token returns 404', function (): void {
    MomGuestAccess::withoutGlobalScopes()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'organization_id' => $this->org->id,
        'token' => 'expired-token',
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('guest.mom', 'expired-token'))->assertNotFound();
});

it('can revoke a guest access link', function (): void {
    $access = MomGuestAccess::withoutGlobalScopes()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'organization_id' => $this->org->id,
        'token' => 'test-token-xyz',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->delete(route('meetings.guest-access.destroy', $access))
        ->assertRedirect();

    expect(MomGuestAccess::withoutGlobalScopes()->find($access->id))->toBeNull();
});
