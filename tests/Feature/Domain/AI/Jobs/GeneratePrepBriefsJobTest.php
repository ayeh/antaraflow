<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\GeneratePrepBriefsJob;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'executive_summary' => 'Brief summary for upcoming meeting.',
                'suggested_questions' => ['What is the timeline?'],
                'reading_priorities' => ['Review the agenda'],
            ])]]],
        ]),
    ]);

    Notification::fake();
});

test('generates briefs for meetings in the next 24 hours', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
    ]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $this->user->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)->count())->toBe(1);

    Notification::assertSentTo($this->user, MeetingPrepBriefNotification::class);
});

test('skips meetings more than 24 hours away', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(48),
    ]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $this->user->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)->count())->toBe(0);

    Notification::assertNothingSent();
});

test('skips finalized and approved meetings', function () {
    $finalizedMeeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
    ]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $finalizedMeeting->id,
        'user_id' => $this->user->id,
    ]);

    $approvedMeeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
    ]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $approvedMeeting->id,
        'user_id' => $this->user->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $finalizedMeeting->id)->count())->toBe(0);
    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $approvedMeeting->id)->count())->toBe(0);

    Notification::assertNothingSent();
});

test('skips meetings with no attendees', function () {
    MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::count())->toBe(0);

    Notification::assertNothingSent();
});

test('marks email_sent_at on briefs after sending notification', function () {
    $meeting = MinutesOfMeeting::factory()->inProgress()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(6),
    ]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $this->user->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    $brief = MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)->first();

    expect($brief->email_sent_at)->not->toBeNull();
});

test('does not send notification for briefs without a user', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
    ]);

    MomAttendee::factory()->external()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)->count())->toBe(1);

    Notification::assertNothingSent();
});
