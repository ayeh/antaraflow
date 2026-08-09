<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Weekly sync',
        'created_by' => $this->user->id,
    ]);
});

test('a first pull returns everything and a usable cursor', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/mobile/v1/sync/pull');

    $response->assertOk()
        ->assertJsonPath('full_resync_required', false)
        ->assertJsonStructure(['changes' => ['meetings', 'action_items'], 'cursor', 'has_more']);

    expect($response->json('changes.meetings.upserted'))->toHaveCount(1);
    expect($response->json('cursor'))->not->toBeEmpty();
});

test('a second pull with the cursor returns only what changed since', function () {
    $cursor = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/sync/pull')
        ->json('cursor');

    $this->travel(2)->seconds();

    MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Newer meeting',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/sync/pull?since='.urlencode($cursor));

    $response->assertOk();

    $titles = collect($response->json('changes.meetings.upserted'))->pluck('title');
    expect($titles)->toContain('Newer meeting')->not->toContain('Weekly sync');
});

test('a soft deleted record comes back in the deleted list', function () {
    $cursor = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/sync/pull')
        ->json('cursor');

    $this->travel(2)->seconds();
    $this->meeting->delete();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/sync/pull?since='.urlencode($cursor));

    $response->assertOk();
    expect($response->json('changes.meetings.deleted'))->toContain($this->meeting->id);
});

test('a cursor older than the tombstone window forces a clean resync', function () {
    $stale = base64_encode((string) json_encode(['t' => now()->subDays(90)->toIso8601String(), 'i' => 0]));

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/sync/pull?since='.urlencode($stale))
        ->assertOk()
        ->assertJsonPath('full_resync_required', true);
});

test('a queued status change is applied', function () {
    $item = ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Prepare the cash forecast',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/sync/push', [
        'operations' => [[
            'client_id' => 'op-1',
            'entity' => 'action_item',
            'op' => 'update',
            'id' => $item->id,
            'payload' => ['status' => 'completed'],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'applied')
        ->assertJsonPath('results.0.client_id', 'op-1');

    expect($item->fresh()->status)->toBe(ActionItemStatus::Completed);
});

test('a write against a stale version is reported as a conflict, not applied', function () {
    $item = ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Original title',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $staleBase = now()->subHour()->toIso8601String();

    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/sync/push', [
        'operations' => [[
            'client_id' => 'op-2',
            'entity' => 'action_item',
            'op' => 'update',
            'id' => $item->id,
            'base_updated_at' => $staleBase,
            'payload' => ['title' => 'Edited offline'],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'conflict')
        ->assertJsonPath('results.0.reason', 'STALE_WRITE');

    expect($item->fresh()->title)->toBe('Original title');
});

test('one rejected operation does not take the rest of the batch down', function () {
    $mine = ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Mine',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/sync/push', [
        'operations' => [
            [
                'client_id' => 'bad',
                'entity' => 'action_item',
                'op' => 'update',
                'id' => 999999,
                'payload' => ['status' => 'completed'],
            ],
            [
                'client_id' => 'good',
                'entity' => 'action_item',
                'op' => 'update',
                'id' => $mine->id,
                'payload' => ['status' => 'completed'],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'rejected')
        ->assertJsonPath('results.0.reason', 'NOT_FOUND')
        ->assertJsonPath('results.1.status', 'applied');

    expect($mine->fresh()->status)->toBe(ActionItemStatus::Completed);
});

test('a queued comment is appended without conflict handling', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/sync/push', [
        'operations' => [[
            'client_id' => 'op-3',
            'entity' => 'comment',
            'op' => 'create',
            'meeting_id' => $this->meeting->id,
            'payload' => ['body' => 'Recorded while offline'],
        ]],
    ]);

    $response->assertOk()->assertJsonPath('results.0.status', 'applied');

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/meetings/{$this->meeting->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Recorded while offline');
});

test('the offline pack carries the meeting, documents and a version stamp', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/meetings/{$this->meeting->id}/pack");

    $response->assertOk()
        ->assertJsonPath('meeting.id', $this->meeting->id)
        ->assertJsonStructure(['meeting', 'attendees', 'documents', 'transcript', 'pack_version', 'generated_at']);

    expect($response->json('pack_version'))->not->toBe('0');
});

test('sync only ever returns the callers own tenant', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    MinutesOfMeeting::createForOrganization($otherOrg->id, [
        'title' => 'Someone elses meeting',
        'created_by' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/mobile/v1/sync/pull');

    $titles = collect($response->json('changes.meetings.upserted'))->pluck('title');
    expect($titles)->not->toContain('Someone elses meeting');
});
