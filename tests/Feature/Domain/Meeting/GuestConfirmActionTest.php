<?php

declare(strict_types=1);

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeRecipient(): MomCirculationRecipient
{
    $org = \App\Domain\Account\Models\Organization::factory()->create();
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

    return MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
    ]);
}

test('guest can confirm a MOM', function () {
    $recipient = makeRecipient();

    $this->post(route('mom.confirm.store', $recipient->token))
        ->assertRedirect(route('mom.confirm', $recipient->token));

    $recipient->refresh();
    expect($recipient->response)->toBe('confirmed');
    expect($recipient->responded_at)->not->toBeNull();
    expect($recipient->responded_ip)->not->toBeNull();
});

test('guest can withdraw confirmation', function () {
    $recipient = makeRecipient();
    $recipient->update([
        'response' => 'confirmed',
        'responded_at' => now(),
    ]);

    $this->delete(route('mom.confirm.destroy', $recipient->token))
        ->assertRedirect(route('mom.confirm', $recipient->token));

    $recipient->refresh();
    expect($recipient->response)->toBeNull();
    expect($recipient->responded_at)->toBeNull();
});

test('cannot confirm past deadline', function () {
    $recipient = makeRecipient();
    $recipient->circulation->update(['deadline_at' => now()->subDay()]);

    $this->post(route('mom.confirm.store', $recipient->token))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($recipient->fresh()->response)->toBeNull();
});

test('guest cannot confirm once the circulation is cancelled', function () {
    $recipient = makeRecipient();
    $recipient->circulation()->update(['status' => 'cancelled', 'closed_at' => now()]);

    $this->post(route('mom.confirm.store', $recipient->token))
        ->assertRedirect(route('mom.confirm', $recipient->token))
        ->assertSessionHas('error', __('mom.circulation_closed'));

    expect($recipient->fresh()->response)->toBeNull();
});

test('guest cannot withdraw once the circulation is cancelled', function () {
    $recipient = makeRecipient();
    $recipient->update(['response' => 'confirmed', 'responded_at' => now()]);
    $recipient->circulation()->update(['status' => 'cancelled', 'closed_at' => now()]);

    $this->delete(route('mom.confirm.destroy', $recipient->token))
        ->assertSessionHas('error', __('mom.circulation_closed'));

    expect($recipient->fresh()->response)->toBe('confirmed');
});

test('guest cannot request amendments once the circulation is cancelled', function () {
    $recipient = makeRecipient();
    $recipient->circulation()->update(['status' => 'cancelled', 'closed_at' => now()]);

    $this->post(route('mom.amendments.store', $recipient->token))
        ->assertSessionHas('error', __('mom.circulation_closed'));

    expect($recipient->fresh()->response)->toBeNull();
});
