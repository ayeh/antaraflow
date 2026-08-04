<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guest can access confirmation page with valid token', function () {
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
        'name' => 'Nor Khamisah',
        'email' => 'khamisah@example.com',
        'token' => Str::random(64),
    ]);

    $response = $this->get(route('mom.confirm', $recipient->token));

    $response->assertOk();
    expect($recipient->fresh()->first_opened_at)->not->toBeNull();
    expect($recipient->fresh()->open_count)->toBe(1);
});

test('invalid token returns 404', function () {
    $response = $this->get(route('mom.confirm', 'invalid-token-that-does-not-exist'));
    $response->assertNotFound();
});

test('visiting twice increments open count', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

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
    ]);

    $this->get(route('mom.confirm', $recipient->token));
    $this->get(route('mom.confirm', $recipient->token));

    expect($recipient->fresh()->open_count)->toBe(2);
    expect($recipient->fresh()->first_opened_at)->not->toBeNull();
    expect($recipient->fresh()->last_opened_at)->not->toBeNull();
});
