<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Board Strategy Review',
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);

    $this->attendee = MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    $this->brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'summary_highlights' => [
            '2 overdue action item(s) require attention.',
            'Review project charter before the meeting.',
            'Discussion on Q3 deliverables expected.',
        ],
        'estimated_prep_minutes' => 25,
    ]);
});

test('sends via mail and database channels', function () {
    Notification::fake();

    $this->user->notify(new MeetingPrepBriefNotification($this->brief));

    Notification::assertSentTo($this->user, MeetingPrepBriefNotification::class, function ($notification, $channels) {
        return in_array('mail', $channels) && in_array('database', $channels);
    });
});

test('builds mail message with correct meeting details', function () {
    $notification = new MeetingPrepBriefNotification($this->brief);

    $mailMessage = $notification->toMail($this->user);

    expect($mailMessage->subject)->toBe('Meeting Prep Brief: Board Strategy Review')
        ->and($mailMessage->greeting)->toBe("Hello {$this->user->name},")
        ->and($mailMessage->actionText)->toBe('View Prep Brief')
        ->and($mailMessage->actionUrl)->toBe(route('meetings.prep-brief', $this->meeting));
});

test('mail message includes summary highlights', function () {
    $notification = new MeetingPrepBriefNotification($this->brief);

    $mailMessage = $notification->toMail($this->user);

    $allLines = collect($mailMessage->introLines)->merge($mailMessage->outroLines)->join(' ');

    expect($allLines)->toContain('2 overdue action item(s) require attention.');
});

test('mail message includes estimated prep time', function () {
    $notification = new MeetingPrepBriefNotification($this->brief);

    $mailMessage = $notification->toMail($this->user);

    $allLines = collect($mailMessage->introLines)->merge($mailMessage->outroLines)->join(' ');

    expect($allLines)->toContain('25 minutes');
});

test('returns correct array data for database notification', function () {
    $notification = new MeetingPrepBriefNotification($this->brief);

    $data = $notification->toArray($this->user);

    expect($data)->toBe([
        'type' => 'meeting_prep_brief',
        'meeting_prep_brief_id' => $this->brief->id,
        'meeting_id' => $this->meeting->id,
        'meeting_title' => 'Board Strategy Review',
        'estimated_prep_minutes' => 25,
    ]);
});
