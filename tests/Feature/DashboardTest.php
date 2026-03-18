<?php

declare(strict_types=1);

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('authenticated user sees dashboard', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Dashboard');
});

test('guest is redirected from dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('stat cards show personalized counts', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('My Actions');
    $response->assertSee('Overdue');
    $response->assertSee('This Week');
    $response->assertSee('Pending Approval');
    $response->assertSee('Completion');
});

test('needs attention banner shows when user has overdue actions', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Needs Attention');
    $response->assertSee('overdue');
});

test('needs attention banner hidden when no urgent items', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertDontSee('Needs Attention');
});

test('needs attention banner shows pending approval when finalized moms exist', function () {
    MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Needs Attention');
    $response->assertSee('pending approval');
});

test('this weeks meetings section shows current week meetings', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Standup',
        'meeting_date' => now()->startOfWeek()->addDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('This Week');
    $response->assertSee('Weekly Standup');
});

test('my action items section shows assigned items', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
        'title' => 'Write quarterly report',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('My Action Items');
    $response->assertSee('Write quarterly report');
});

test('recent activity shows mom audit log entries', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Board Meeting',
    ]);

    AuditLog::factory()->create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'action' => 'created',
        'auditable_type' => MinutesOfMeeting::class,
        'auditable_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Recent Activity');
    $response->assertSee('Board Meeting');
});
