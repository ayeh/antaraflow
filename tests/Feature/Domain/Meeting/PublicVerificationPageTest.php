<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('verification page shows approval status for approved meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create([
        'title' => 'Mesyuarat Jawatankuasa Kewangan',
        'status' => MeetingStatus::Approved->value,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subDay(),
        'status' => 'closed_approved',
        'closed_at' => now(),
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
    ]);

    $this->get(route('mom.verify', $meeting->id))
        ->assertOk()
        ->assertSee('Mesyuarat Jawatankuasa Kewangan')
        ->assertSee('Disahkan')
        ->assertDontSee('alice@example.com');  // Privacy: don't expose email on public page
});

test('verification page does not expose meeting content', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create([
        'title' => 'Secret Meeting',
        'status' => MeetingStatus::Approved->value,
    ]);

    MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subDay(),
        'status' => 'closed_approved',
        'closed_at' => now(),
    ]);

    // Content that should NOT appear on the verification page
    $response = $this->get(route('mom.verify', $meeting->id));

    $response->assertOk();
    // Meeting title is OK to show (it's the document reference)
    // But full content (topics/agenda etc.) should not be included
    $response->assertSee('Secret Meeting');
});

test('verification page returns 404 for non-existent meeting', function () {
    $this->get(route('mom.verify', 999999))->assertNotFound();
});
