<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Jobs\CheckOverdueActionItemsJob;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Notifications\ActionItemAssignedNotification;
use App\Domain\ActionItem\Notifications\ActionItemOverdueNotification;
use App\Domain\Attendee\Notifications\MeetingInviteNotification;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Notifications\MeetingFinalizedNotification;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('action item assigned notification can be sent', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    $this->user->notify(new ActionItemAssignedNotification($item));

    Notification::assertSentTo($this->user, ActionItemAssignedNotification::class);
});

test('action item overdue notification contains correct data', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $item = ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new ActionItemOverdueNotification($item);
    $data = $notification->toArray($this->user);

    expect($data['type'])->toBe('action_item_overdue');
    expect($data['action_item_id'])->toBe($item->id);
    expect($data['days_overdue'])->toBeGreaterThan(0);
});

test('meeting finalized notification contains correct data', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new MeetingFinalizedNotification($meeting);
    $data = $notification->toArray($this->user);

    expect($data['type'])->toBe('meeting_finalized');
    expect($data['meeting_id'])->toBe($meeting->id);
    expect($data['title'])->toBe($meeting->title);
});

test('meeting invite notification contains correct data', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new MeetingInviteNotification($meeting);
    $data = $notification->toArray($this->user);

    expect($data['type'])->toBe('meeting_invite');
    expect($data['meeting_id'])->toBe($meeting->id);
});

test('check overdue action items job sends notifications', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->completed()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'due_date' => now()->subDays(5),
    ]);

    (new CheckOverdueActionItemsJob)->handle();

    Notification::assertSentToTimes($this->user, ActionItemOverdueNotification::class, 1);
});

test('check overdue action items job skips items without assignee', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => null,
        'created_by' => $this->user->id,
    ]);

    (new CheckOverdueActionItemsJob)->handle();

    Notification::assertNothingSent();
});
