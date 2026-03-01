<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('authenticated user can share meeting with another user', function () {
    $otherUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($otherUser, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($this->user)->post(route('meetings.shares.store', $this->meeting), [
        'shared_with_user_id' => $otherUser->id,
        'permission' => 'view',
        'is_link_share' => false,
    ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseHas('meeting_shares', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'shared_with_user_id' => $otherUser->id,
        'permission' => SharePermission::View->value,
    ]);
});

test('authenticated user can generate a share link', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.shares.store', $this->meeting), [
        'permission' => 'comment',
        'is_link_share' => true,
    ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));

    $share = MeetingShare::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->whereNull('shared_with_user_id')
        ->first();

    expect($share)->not->toBeNull();
    expect($share->share_token)->not->toBeNull();
});

test('authenticated user can revoke a share', function () {
    $otherUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $share = MeetingShare::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'shared_with_user_id' => $otherUser->id,
        'shared_by_user_id' => $this->user->id,
        'permission' => SharePermission::View,
    ]);

    $response = $this->actingAs($this->user)->delete(route('meetings.shares.destroy', [$this->meeting, $share]));

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertSoftDeleted('meeting_shares', ['id' => $share->id]);
});

test('unauthenticated user cannot access share routes', function () {
    $response = $this->get(route('meetings.shares.index', $this->meeting));

    $response->assertRedirect(route('login'));
});
