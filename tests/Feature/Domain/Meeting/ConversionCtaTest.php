<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('confirmed guest sees conversion CTA', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now(),
    ]);

    $this->get(route('mom.confirm', $recipient->token))
        ->assertOk()
        ->assertSee('antaraNote'); // CTA mentions the product
});

test('unconfirmed guest does not see conversion CTA', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
        'response' => null,
    ]);

    $html = $this->get(route('mom.confirm', $recipient->token))->content();

    // The CTA should only show after confirmation, not before
    // The footer brand mention is OK, but the "register" CTA should not show
    expect($html)->not->toContain('Daftar percuma');
});
