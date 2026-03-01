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
