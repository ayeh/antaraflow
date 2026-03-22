<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('full meeting lifecycle: create, edit, add attendees, finalize, approve', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Sprint Planning',
        'meeting_date' => now()->addDay()->format('Y-m-d'),
        'location' => 'Conference Room A',
        'prepared_by' => 'Test User',
    ]);
    $response->assertRedirect();

    $meeting = MinutesOfMeeting::query()->latest('id')->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->status)->toBe(MeetingStatus::Draft);
    expect($meeting->title)->toBe('Sprint Planning');

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Sprint Planning v2',
        'content' => 'Discussed sprint goals and backlog prioritization.',
    ])->assertRedirect();

    $meeting->refresh();
    expect($meeting->title)->toBe('Sprint Planning v2');

    $this->actingAs($this->user)->post(
        route('meetings.attendees.store', $meeting),
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'participant',
        ]
    )->assertRedirect();

    expect($meeting->attendees()->count())->toBe(1);

    $this->actingAs($this->user)->post(route('meetings.finalize', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Finalized);
    expect($meeting->versions()->count())->toBeGreaterThanOrEqual(1);

    $this->actingAs($this->user)->post(route('meetings.approve', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Approved);
});

test('cannot edit an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Updated title',
    ])->assertForbidden();
});

test('viewer cannot create or finalize meetings', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $this->actingAs($viewer)->post(route('meetings.store'), [
        'title' => 'Should not work',
        'meeting_date' => '2026-04-01',
        'prepared_by' => 'Viewer User',
    ])->assertForbidden();

    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($viewer)->post(route('meetings.finalize', $meeting))
        ->assertForbidden();
});

test('full wizard lifecycle: create with project, add attendees, add notes, add action items, finalize, approve', function () {
    // 1. Create a project first
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
    ]);

    // 2. Create meeting via wizard form with new fields
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Wizard Test Meeting',
        'project_id' => $project->id,
        'meeting_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'language' => 'ms',
        'prepared_by' => $this->user->name,
    ]);
    $response->assertRedirect();

    $meeting = MinutesOfMeeting::query()->latest('id')->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->status)->toBe(MeetingStatus::Draft);
    expect($meeting->mom_number)->toStartWith('MOM-2026-');
    expect($meeting->project_id)->toBe($project->id);

    // 3. View wizard step 1
    $this->actingAs($this->user)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee($meeting->title);

    // 4. Step 2: Add attendees
    $this->actingAs($this->user)->post(
        route('meetings.attendees.store', $meeting),
        [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'role' => 'participant',
        ]
    )->assertRedirect();

    expect($meeting->attendees()->count())->toBe(1);

    // 5. Step 3: Add manual note
    $this->actingAs($this->user)->post(
        route('meetings.manual-notes.store', $meeting),
        ['content' => 'Key discussion point about project timeline.']
    )->assertRedirect();

    expect($meeting->manualNotes()->count())->toBe(1);

    // 6. Step 4: Add action item
    $this->actingAs($this->user)->post(
        route('meetings.action-items.store', $meeting),
        [
            'title' => 'Follow up with client on deliverables',
            'priority' => 'high',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]
    )->assertRedirect();

    expect($meeting->actionItems()->count())->toBe(1);

    // 7. Update meeting with wizard fields (Step 1 edit)
    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Wizard Test Meeting - Updated',
        'summary' => 'Meeting focused on Q2 deliverables.',
    ])->assertRedirect();

    $meeting->refresh();
    expect($meeting->title)->toBe('Wizard Test Meeting - Updated');

    // 8. Step 5: Finalize
    $this->actingAs($this->user)
        ->post(route('meetings.finalize', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Finalized);
    expect($meeting->versions()->count())->toBeGreaterThanOrEqual(1);

    // 9. Revert finalized meeting back to draft
    $this->actingAs($this->user)
        ->post(route('meetings.revert', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Draft);

    // 10. Verify edit redirects to wizard after revert
    $this->actingAs($this->user)
        ->get(route('meetings.edit', $meeting))
        ->assertRedirect(route('meetings.show', ['meeting' => $meeting, 'step' => 1]));

    // 11. Re-finalize and approve
    $this->actingAs($this->user)
        ->post(route('meetings.finalize', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Finalized);

    $this->actingAs($this->user)
        ->post(route('meetings.approve', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Approved);
});
