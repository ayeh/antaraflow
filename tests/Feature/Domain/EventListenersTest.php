<?php

declare(strict_types=1);

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Notifications\ExtractionCompletedNotification;
use App\Domain\AI\Notifications\ExtractionFailedNotification;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Notifications\MeetingApprovedNotification;
use App\Domain\Meeting\Notifications\MeetingFinalizedNotification;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Notifications\TranscriptionCompletedNotification;
use App\Domain\Transcription\Notifications\TranscriptionFailedNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('notifies creator when transcription completes', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    TranscriptionCompleted::dispatch($transcription);

    Notification::assertSentTo($this->user, TranscriptionCompletedNotification::class);
});

it('notifies creator when transcription fails', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    TranscriptionFailed::dispatch($transcription);

    Notification::assertSentTo($this->user, TranscriptionFailedNotification::class);
});

it('notifies creator when extraction completes', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);

    ExtractionCompleted::dispatch($meeting);

    Notification::assertSentTo($this->user, ExtractionCompletedNotification::class);
});

it('notifies creator when extraction fails', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);

    ExtractionFailed::dispatch($meeting, 'Test error');

    Notification::assertSentTo($this->user, ExtractionFailedNotification::class);
});

it('notifies attendees when meeting is finalized', function () {
    Notification::fake();

    $attendeeUser = User::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $meeting->attendees()->create([
        'user_id' => $attendeeUser->id,
        'name' => $attendeeUser->name,
        'email' => $attendeeUser->email,
    ]);

    MeetingFinalized::dispatch($meeting, $this->user);

    Notification::assertSentTo($attendeeUser, MeetingFinalizedNotification::class);
});

it('notifies attendees when meeting is approved', function () {
    Notification::fake();

    $attendeeUser = User::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $meeting->attendees()->create([
        'user_id' => $attendeeUser->id,
        'name' => $attendeeUser->name,
        'email' => $attendeeUser->email,
    ]);

    MeetingApproved::dispatch($meeting, $this->user);

    Notification::assertSentTo($attendeeUser, MeetingApprovedNotification::class);
});
