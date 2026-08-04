<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guest can submit a remark on a topic', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $topic = MomTopic::factory()->create(['minutes_of_meeting_id' => $meeting->id]);

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

    $response = $this->post(route('mom.remark', $recipient->token), [
        'commentable_type' => 'topic',
        'commentable_id' => $topic->id,
        'body' => 'Kos yang dibincang RM50k, bukan RM15k.',
    ]);

    $response->assertRedirect();

    $comment = Comment::first();
    expect($comment)->not->toBeNull();
    expect($comment->body)->toBe('Kos yang dibincang RM50k, bukan RM15k.');
    expect($comment->user_id)->toBeNull();
    expect($comment->mom_circulation_recipient_id)->toBe($recipient->id);
    expect($comment->commentable_type)->toContain('MomTopic');
    expect($comment->commentable_id)->toBe($topic->id);
    expect($comment->client_visible)->toBeTrue();
});

test('remark sets recipient response to amendment_requested', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $topic = MomTopic::factory()->create(['minutes_of_meeting_id' => $meeting->id]);

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
    ]);

    $this->post(route('mom.remark', $recipient->token), [
        'commentable_type' => 'topic',
        'commentable_id' => $topic->id,
        'body' => 'Ada kesilapan pada tarikh.',
    ])->assertRedirect(route('mom.confirm', $recipient->token));

    expect($recipient->fresh()->response)->toBe('amendment_requested');
});

test('remark body is required', function () {
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
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'token' => Str::random(64),
    ]);

    $this->post(route('mom.remark', $recipient->token), [
        'commentable_type' => 'topic',
        'commentable_id' => 1,
        'body' => '',
    ])->assertSessionHasErrors('body');
});

test('remark is rejected past deadline', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->pendingConfirmation()->create();

    $topic = MomTopic::factory()->create(['minutes_of_meeting_id' => $meeting->id]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subDay(),
        'status' => 'open',
    ]);

    $recipient = MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'token' => Str::random(64),
    ]);

    $this->post(route('mom.remark', $recipient->token), [
        'commentable_type' => 'topic',
        'commentable_id' => $topic->id,
        'body' => 'Too late remark.',
    ])->assertRedirect()->assertSessionHas('error');

    expect(Comment::count())->toBe(0);
});
