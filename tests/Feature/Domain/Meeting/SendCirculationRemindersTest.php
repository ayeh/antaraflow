<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Jobs\SendCirculationReminders;
use App\Domain\Meeting\Mail\CirculationReminderMail;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('sends reminder to recipients who have not responded near deadline', function () {
    Mail::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->addHours(20),
        'status' => 'open',
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
    ]);

    (new SendCirculationReminders)->handle();

    Mail::assertSentCount(1);
    Mail::assertSent(CirculationReminderMail::class, fn ($mail) => $mail->hasTo('alice@example.com')
    );
});

test('does not send reminder if more than 24h remain', function () {
    Mail::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
    ]);

    (new SendCirculationReminders)->handle();

    Mail::assertNothingSent();
});

test('does not send reminder to already-confirmed recipients', function () {
    Mail::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->addHours(20),
        'status' => 'open',
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
    ]);

    (new SendCirculationReminders)->handle();

    Mail::assertNothingSent();
});
