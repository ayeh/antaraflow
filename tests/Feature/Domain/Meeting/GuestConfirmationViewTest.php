<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('confirmation page shows recipient name and meeting title', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create([
        'title' => 'Mesyuarat Jawatankuasa',
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Nor Khamisah',
        'email' => 'khamisah@example.com',
        'token' => Str::random(64),
    ]);

    $this->get(route('mom.confirm', $recipient->token))
        ->assertOk()
        ->assertSee('Nor Khamisah')
        ->assertSee('Mesyuarat Jawatankuasa');
});

test('confirmation page shows deadline banner with deadline text', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan',
        'deadline_at' => now()->addDays(5),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Ahmad Razif',
        'email' => 'razif@example.com',
        'token' => Str::random(64),
    ]);

    $this->get(route('mom.confirm', $recipient->token))
        ->assertOk()
        ->assertSee('Sila sahkan sebelum')
        ->assertSee('Tiada maklum balas akan dianggap sebagai pengesahan');
});

test('confirmation page shows unconfirmed action bar when not yet confirmed', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Farid Hassan',
        'email' => 'farid@example.com',
        'token' => Str::random(64),
        'response' => null,
    ]);

    $this->get(route('mom.confirm', $recipient->token))
        ->assertOk()
        ->assertSee('Sahkan Minit');
});

test('confirmation page shows confirmed state when already confirmed', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Siti Nabilah',
        'email' => 'nabilah@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now(),
    ]);

    $this->get(route('mom.confirm', $recipient->token))
        ->assertOk()
        ->assertSee('Anda telah mengesahkan minit ini');
});
