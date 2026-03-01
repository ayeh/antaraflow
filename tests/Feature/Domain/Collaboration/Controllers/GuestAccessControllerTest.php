<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
        'title' => 'Q1 Planning Session',
    ]);
});

test('guest can view meeting with valid share token', function () {
    $share = MeetingShare::factory()->guestLink()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'shared_by_user_id' => $this->owner->id,
        'permission' => SharePermission::View,
        'expires_at' => null,
    ]);

    $response = $this->get(route('guest.meeting', $share->share_token));

    $response->assertStatus(200);
    $response->assertSee('Q1 Planning Session');
});

test('expired share token returns 410', function () {
    $share = MeetingShare::factory()->guestLink()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'shared_by_user_id' => $this->owner->id,
        'permission' => SharePermission::View,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('guest.meeting', $share->share_token));

    $response->assertStatus(410);
});

test('invalid share token returns 404', function () {
    $response = $this->get(route('guest.meeting', 'invalid-token-that-does-not-exist'));

    $response->assertStatus(404);
});

test('user-specific share token is not accessible as guest', function () {
    $specificUser = User::factory()->create(['current_organization_id' => $this->org->id]);

    $share = MeetingShare::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'shared_with_user_id' => $specificUser->id,
        'shared_by_user_id' => $this->owner->id,
        'permission' => SharePermission::View,
        'expires_at' => null,
    ]);

    $response = $this->get(route('guest.meeting', $share->share_token));

    $response->assertStatus(404);
});
