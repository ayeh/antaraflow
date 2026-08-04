<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Jobs\CloseExpiredCirculationsJob;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('auto-approves meeting when deadline passes with no hold conditions', function () {
    Event::fake([\App\Domain\Meeting\Events\MeetingApproved::class]);

    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subMinutes(5),
        'status' => 'open',
    ]);

    // Recipient opened the MOM but didn't respond (silent = deemed confirmed)
    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
        'first_opened_at' => now()->subHours(2),
        'open_count' => 1,
    ]);

    (new CloseExpiredCirculationsJob)->handle();

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Approved);
    expect($circulation->fresh()->status)->toBe('closed_approved');
    Event::assertDispatched(\App\Domain\Meeting\Events\MeetingApproved::class);
});

test('holds auto-approve when nobody opened the MOM', function () {
    Event::fake([\App\Domain\Meeting\Events\MeetingApproved::class]);

    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subMinutes(5),
        'status' => 'open',
    ]);

    // Nobody opened — open_count = 0 for all
    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
        'open_count' => 0,
    ]);

    (new CloseExpiredCirculationsJob)->handle();

    expect($meeting->fresh()->status)->toBe(MeetingStatus::PendingConfirmation);
    expect($circulation->fresh()->status)->toBe('awaiting_secretary');
    Event::assertNotDispatched(\App\Domain\Meeting\Events\MeetingApproved::class);
});

test('holds auto-approve when there are unresolved amendments', function () {
    Event::fake([\App\Domain\Meeting\Events\MeetingApproved::class]);

    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subMinutes(5),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'token' => Str::random(64),
        'open_count' => 1,
        'response' => 'amendment_requested',
    ]);

    // Unresolved comment
    \App\Domain\Collaboration\Models\Comment::createForOrganization($org->id, [
        'user_id' => null,
        'mom_circulation_recipient_id' => $recipient->id,
        'commentable_type' => 'App\\Domain\\AI\\Models\\MomTopic',
        'commentable_id' => 1,
        'body' => 'Kos salah.',
        'client_visible' => true,
        'resolved_at' => null,
    ]);

    (new CloseExpiredCirculationsJob)->handle();

    expect($meeting->fresh()->status)->toBe(MeetingStatus::PendingConfirmation);
    expect($circulation->fresh()->status)->toBe('awaiting_secretary');
    Event::assertNotDispatched(\App\Domain\Meeting\Events\MeetingApproved::class);
});
