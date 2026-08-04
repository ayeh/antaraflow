<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('meeting show page displays monitoring panel when pending confirmation', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan minit mesyuarat',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now(),
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
    ]);

    $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('Bob');
});

test('monitoring panel is absent when meeting is not pending confirmation', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertDontSee('Circulation Monitor');
});

test('monitoring panel shows not-opened recipients before responded recipients', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

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
        'name' => 'Charlie Confirmed',
        'email' => 'charlie@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now(),
        'open_count' => 2,
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Dave Unopened',
        'email' => 'dave@example.com',
        'token' => Str::random(64),
        'open_count' => 0,
    ]);

    $response = $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Dave Unopened')
        ->assertSee('Charlie Confirmed')
        ->assertSee('Not opened');

    // Dave (not opened) must appear before Charlie (confirmed) in the HTML
    $html = $response->getContent();
    $davePos = strpos($html, 'Dave Unopened');
    $charliePos = strpos($html, 'Charlie Confirmed');
    expect($davePos)->toBeLessThan($charliePos);
});
