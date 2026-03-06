<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('governance analytics page loads successfully', function () {
    $response = $this->actingAs($this->user)->get(route('analytics.governance'));

    $response->assertSuccessful();
    $response->assertViewIs('analytics.governance');
});

test('meeting cost calculation returns correct total', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'duration_minutes' => 60,
        'meeting_date' => now()->subDays(5),
    ]);

    MomAttendee::factory()->count(3)->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.data', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('cost_estimate.meeting_count', 1);

    $data = $response->json('cost_estimate');
    // 1 hour * $50/hr * 3 attendees = $150
    expect((float) $data['total_cost'])->toBe(150.0);
    expect((float) $data['avg_cost_per_meeting'])->toBe(150.0);
});

test('attendance rate calculates present over total correctly', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->subDays(5),
    ]);

    MomAttendee::factory()->count(2)->present()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    MomAttendee::factory()->count(3)->create([
        'minutes_of_meeting_id' => $meeting->id,
        'is_present' => false,
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.data', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();

    $trends = $response->json('attendance_trends');
    expect($trends)->toHaveCount(1);
    // 2 present out of 5 total = 40%
    expect($trends[0]['present'])->toBe(2);
    expect($trends[0]['total'])->toBe(5);
    expect((float) $trends[0]['rate'])->toBe(40.0);
});

test('action item completion trends include overdue count', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->subDays(10),
    ]);

    // Completed on time
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Completed,
        'due_date' => now()->addDays(5),
        'completed_at' => now(),
        'created_at' => now()->subDays(5),
    ]);

    // Completed overdue
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Completed,
        'due_date' => now()->subDays(10),
        'completed_at' => now()->subDays(2),
        'created_at' => now()->subDays(5),
    ]);

    // Still open
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
        'due_date' => now()->addDays(10),
        'created_at' => now()->subDays(5),
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.data', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();

    $trends = $response->json('action_item_trends');
    expect($trends)->toHaveCount(1);
    expect($trends[0]['completed_on_time'])->toBe(1);
    expect($trends[0]['completed_overdue'])->toBe(1);
    expect($trends[0]['still_open'])->toBe(1);
});

test('meeting type distribution groups meetings correctly', function () {
    MinutesOfMeeting::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->subDays(5),
        'meeting_type' => 'standup',
    ]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->subDays(3),
        'meeting_type' => 'board_meeting',
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.data', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();

    $distribution = $response->json('meeting_type_distribution');
    expect($distribution)->toHaveKey('standup', 2);
    expect($distribution)->toHaveKey('board_meeting', 1);
});

test('date range filtering only includes meetings in range', function () {
    // In-range meeting
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'duration_minutes' => 60,
        'meeting_date' => now()->subDays(5),
    ]);

    // Out-of-range meeting
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'duration_minutes' => 60,
        'meeting_date' => now()->subMonths(3),
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.data', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('cost_estimate.meeting_count', 1);
});

test('csv export returns downloadable file', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'duration_minutes' => 60,
        'meeting_date' => now()->subDays(5),
    ]);

    $response = $this->actingAs($this->user)->get(route('analytics.governance.export', [
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDownload();
});
