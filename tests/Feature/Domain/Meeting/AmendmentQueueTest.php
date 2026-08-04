<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('secretary can accept a remark as minor correction', function (): void {
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

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'token' => Str::random(64),
        'response' => 'amendment_requested',
    ]);

    $comment = Comment::createForOrganization($org->id, [
        'user_id' => null,
        'mom_circulation_recipient_id' => $recipient->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $meeting->id,
        'body' => 'Kos RM50k bukan RM15k.',
        'client_visible' => true,
    ]);

    $this->actingAs($user)
        ->post(route('meetings.amendment.decide', [$meeting, $comment]), [
            'decision' => 'minor',
        ])
        ->assertRedirect(route('meetings.show', $meeting));

    $fresh = $comment->fresh();
    expect($fresh->resolved_at)->not->toBeNull();
    expect($fresh->resolution)->toBe('minor');
});

test('secretary can reject a remark', function (): void {
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

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
        'response' => 'amendment_requested',
    ]);

    $comment = Comment::createForOrganization($org->id, [
        'user_id' => null,
        'mom_circulation_recipient_id' => $recipient->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $meeting->id,
        'body' => 'Wrong claim.',
        'client_visible' => true,
    ]);

    $this->actingAs($user)
        ->post(route('meetings.amendment.decide', [$meeting, $comment]), [
            'decision' => 'reject',
        ])
        ->assertRedirect(route('meetings.show', $meeting));

    $fresh = $comment->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->resolved_at)->not->toBeNull();
    expect($fresh->resolution)->toBe('rejected');
});

test('secretary can accept a remark as material amendment triggering round 2', function (): void {
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
        'subject' => 'Sila sahkan minit',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now(),
    ]);

    $comment = Comment::createForOrganization($org->id, [
        'user_id' => null,
        'mom_circulation_recipient_id' => $recipient->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $meeting->id,
        'body' => 'Tarikh mesyuarat salah.',
        'client_visible' => true,
    ]);

    $this->actingAs($user)
        ->post(route('meetings.amendment.decide', [$meeting, $comment]), [
            'decision' => 'material',
        ])
        ->assertRedirect(route('meetings.show', $meeting));

    // Comment resolved as material
    $fresh = $comment->fresh();
    expect($fresh->resolution)->toBe('material');
    expect($fresh->resolved_at)->not->toBeNull();

    // Old circulation is closed
    expect($circulation->fresh()->status)->toBe('closed_amended');

    // Previous confirmation invalidated
    expect($recipient->fresh()->responded_at)->toBeNull();
    expect($recipient->fresh()->response)->toBeNull();

    // Round 2 circulation created
    $round2 = $meeting->circulations()->where('round', 2)->first();
    expect($round2)->not->toBeNull();
    expect($round2->status)->toBe('open');
    expect($round2->recipients)->toHaveCount(1);
    expect($round2->recipients->first()->email)->toBe('carol@example.com');
});

test('invalid decision is rejected with validation error', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $comment = Comment::createForOrganization($org->id, [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $meeting->id,
        'body' => 'Some remark.',
    ]);

    $this->actingAs($user)
        ->post(route('meetings.amendment.decide', [$meeting, $comment]), [
            'decision' => 'invalid-value',
        ])
        ->assertSessionHasErrors('decision');
});

test('unauthenticated user cannot access amendment decision route', function (): void {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $org->id,
    ]);
    $comment = Comment::createForOrganization($org->id, [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $meeting->id,
        'body' => 'Some remark.',
    ]);

    $this->post(route('meetings.amendment.decide', [$meeting, $comment]), [
        'decision' => 'minor',
    ])
        ->assertRedirect(route('login'));
});
