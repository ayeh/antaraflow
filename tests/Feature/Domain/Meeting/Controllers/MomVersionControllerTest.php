<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('org member can view version history for a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 1,
        'change_summary' => 'Initial draft saved',
    ]);

    MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 2,
        'change_summary' => 'Updated agenda section',
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.versions.index', $meeting));

    $response->assertOk();
    $response->assertSee('Initial draft saved');
    $response->assertSee('Updated agenda section');
});

test('org member can view a specific version', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $version = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 3,
        'change_summary' => 'Reviewed action items',
        'snapshot' => [
            'title' => 'Q1 Planning Meeting',
            'summary' => 'A planning session',
            'content' => 'Detailed meeting content here.',
            'status' => 'draft',
            'metadata' => null,
        ],
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.versions.show', [$meeting, $version]));

    $response->assertOk();
    $response->assertSee('3');
    $response->assertSee('Reviewed action items');
});

test('version history is not accessible from other org', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    $response = $this->actingAs($otherUser)->get(route('meetings.versions.index', $meeting));

    $response->assertStatus(404);
});

test('unauthenticated user cannot view version history', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('meetings.versions.index', $meeting));

    $response->assertRedirect(route('login'));
});

test('restoring a version rewrites the meeting and redirects', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Current title',
        'content' => 'Current content',
    ]);

    $version = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 1,
        'snapshot' => [
            'title' => 'Earlier title',
            'summary' => 'Earlier summary',
            'content' => 'Earlier content',
            'status' => 'draft',
            'metadata' => null,
        ],
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.versions.restore', [$meeting, $version]))
        ->assertRedirect(route('meetings.show', $meeting));

    expect($meeting->fresh()->title)->toBe('Earlier title')
        ->and($meeting->fresh()->content)->toBe('Earlier content');
});

test('a locked meeting refuses a version restore', function ($state) {
    $meeting = MinutesOfMeeting::factory()->{$state}()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Locked content',
    ]);

    $version = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 1,
        'snapshot' => ['title' => 'Earlier', 'summary' => null, 'content' => 'Earlier content', 'status' => 'draft', 'metadata' => null],
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.versions.restore', [$meeting, $version]))
        ->assertForbidden();

    expect($meeting->fresh()->content)->toBe('Locked content');
})->with(['finalized', 'pendingConfirmation', 'approved']);

test('a version from another meeting cannot be restored', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Untouched',
    ]);

    $otherMeeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $foreignVersion = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $otherMeeting->id,
        'created_by' => $this->user->id,
        'version_number' => 1,
        'snapshot' => ['title' => 'Foreign', 'summary' => null, 'content' => 'Foreign content', 'status' => 'draft', 'metadata' => null],
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.versions.restore', [$meeting, $foreignVersion]))
        ->assertStatus(404);

    expect($meeting->fresh()->content)->toBe('Untouched');
});

test('another org cannot restore a version', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Untouched',
    ]);

    $version = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user->id,
        'version_number' => 1,
        'snapshot' => ['title' => 'Earlier', 'summary' => null, 'content' => 'Earlier content', 'status' => 'draft', 'metadata' => null],
    ]);

    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    $this->actingAs($otherUser)
        ->post(route('meetings.versions.restore', [$meeting, $version]))
        ->assertStatus(404);

    expect($meeting->fresh()->content)->toBe('Untouched');
});

test('the history page offers restore only while the meeting is editable', function () {
    $draft = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    foreach ([1, 2] as $number) {
        MomVersion::factory()->create([
            'minutes_of_meeting_id' => $draft->id,
            'created_by' => $this->user->id,
            'version_number' => $number,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('meetings.versions.index', $draft))
        ->assertSee(route('meetings.versions.restore', [$draft, 1]));

    $draft->update(['status' => \App\Support\Enums\MeetingStatus::Finalized]);

    $this->actingAs($this->user)
        ->get(route('meetings.versions.index', $draft))
        ->assertDontSee('/restore')
        ->assertSee('These minutes are locked');
});
