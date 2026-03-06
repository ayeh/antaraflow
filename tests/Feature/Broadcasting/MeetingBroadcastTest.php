<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Events\ActionItemUpdated;
use App\Events\AttendeePresenceChanged;
use App\Events\CommentAdded;
use App\Events\MeetingStatusChanged;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('CommentAdded event broadcasts on correct private channel', function () {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'body' => 'Test comment',
    ]);

    $event = new CommentAdded($comment, $this->meeting->id);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-meeting.{$this->meeting->id}");

    $payload = $event->broadcastWith();
    expect($payload)
        ->toHaveKey('id', $comment->id)
        ->toHaveKey('body', 'Test comment')
        ->toHaveKey('user_id', $this->user->id)
        ->toHaveKey('user_name')
        ->toHaveKey('created_at');
});

test('MeetingStatusChanged event broadcasts on correct private channel', function () {
    $event = new MeetingStatusChanged($this->meeting, MeetingStatus::Finalized, $this->user->name);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-meeting.{$this->meeting->id}");

    $payload = $event->broadcastWith();
    expect($payload)
        ->toHaveKey('meeting_id', $this->meeting->id)
        ->toHaveKey('status', 'finalized')
        ->toHaveKey('changed_by', $this->user->name);
});

test('ActionItemUpdated event broadcasts on correct private channel', function () {
    $actionItem = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $event = new ActionItemUpdated($actionItem);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-meeting.{$this->meeting->id}");

    $payload = $event->broadcastWith();
    expect($payload)
        ->toHaveKey('id', $actionItem->id)
        ->toHaveKey('title', $actionItem->title)
        ->toHaveKey('status', $actionItem->status->value);
});

test('meeting channel auth allows organization member', function () {
    $meeting = MinutesOfMeeting::find($this->meeting->id);

    $authorized = $meeting && $meeting->organization_id === $this->user->current_organization_id;

    expect($authorized)->toBeTrue();
});

test('meeting channel auth rejects non-member', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => 'owner']);

    $meeting = MinutesOfMeeting::find($this->meeting->id);

    $authorized = $meeting && $meeting->organization_id === $otherUser->current_organization_id;

    expect($authorized)->toBeFalse();
});

test('presence channel returns user data for authorized member', function () {
    $event = new AttendeePresenceChanged(
        meetingId: $this->meeting->id,
        userId: $this->user->id,
        userName: $this->user->name,
        action: 'joined',
    );

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe("presence-meeting.{$this->meeting->id}.presence");

    $payload = $event->broadcastWith();
    expect($payload)
        ->toHaveKey('user_id', $this->user->id)
        ->toHaveKey('user_name', $this->user->name)
        ->toHaveKey('action', 'joined');
});
