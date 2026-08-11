<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * OrganizationScope keys off auth()->user()->current_organization_id and falls
 * back to `1 = 0` when there is no auth context. It has previously returned
 * nothing at all under guards other than "web", which would make every mobile
 * list silently empty — or, if it failed open, leak across tenants. These tests
 * pin both halves of that behaviour under the sanctum guard.
 */
beforeEach(function () {
    $this->orgA = Organization::factory()->create(['name' => 'Alpha']);
    $this->orgB = Organization::factory()->create(['name' => 'Beta']);

    $this->userA = User::factory()->create(['current_organization_id' => $this->orgA->id]);
    $this->orgA->members()->attach($this->userA, ['role' => UserRole::Owner->value]);

    $this->userB = User::factory()->create(['current_organization_id' => $this->orgB->id]);
    $this->orgB->members()->attach($this->userB, ['role' => UserRole::Owner->value]);

    $this->meetingA = MinutesOfMeeting::createForOrganization($this->orgA->id, [
        'title' => 'Alpha board meeting',
        'created_by' => $this->userA->id,
    ]);

    $this->meetingB = MinutesOfMeeting::createForOrganization($this->orgB->id, [
        'title' => 'Beta board meeting',
        'created_by' => $this->userB->id,
    ]);
});

test('the scope is active under sanctum and returns the tenant rows', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/mobile/v1/meetings');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Alpha board meeting');
});

test('meetings from another organization are not listed', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/mobile/v1/meetings');

    expect(collect($response->json('data'))->pluck('title'))
        ->not->toContain('Beta board meeting');
});

test('a meeting from another organization cannot be read directly', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/mobile/v1/meetings/{$this->meetingB->id}")
        ->assertStatus(404);
});

test('a meeting from another organization cannot be updated', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/mobile/v1/meetings/{$this->meetingB->id}", ['title' => 'Hijacked'])
        ->assertStatus(404);

    expect($this->meetingB->fresh()->title)->toBe('Beta board meeting');
});

test('a meeting from another organization cannot be deleted', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/mobile/v1/meetings/{$this->meetingB->id}")
        ->assertStatus(404);

    expect(MinutesOfMeeting::withoutGlobalScopes()->find($this->meetingB->id))->not->toBeNull();
});

test('action items are scoped to the tenant as well', function () {
    ActionItem::createForOrganization($this->orgA->id, [
        'minutes_of_meeting_id' => $this->meetingA->id,
        'title' => 'Alpha task',
        'created_by' => $this->userA->id,
        'assigned_to' => $this->userA->id,
    ]);

    ActionItem::createForOrganization($this->orgB->id, [
        'minutes_of_meeting_id' => $this->meetingB->id,
        'title' => 'Beta task',
        'created_by' => $this->userB->id,
        'assigned_to' => $this->userB->id,
    ]);

    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?assigned_to_me=0');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('Alpha task');
});

test('the X-Organization-Id header switches tenant for a member of both', function () {
    $this->orgB->members()->attach($this->userA, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Organization-Id', (string) $this->orgB->id)
        ->getJson('/api/mobile/v1/meetings');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Beta board meeting');
});

test('the X-Organization-Id header is refused for a non-member', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Organization-Id', (string) $this->orgB->id)
        ->getJson('/api/mobile/v1/meetings')
        ->assertStatus(403)
        ->assertJsonPath('code', 'ORGANIZATION_FORBIDDEN');
});

test('the header override does not persist to the users stored organization', function () {
    $this->orgB->members()->attach($this->userA, ['role' => UserRole::Member->value]);

    $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Organization-Id', (string) $this->orgB->id)
        ->getJson('/api/mobile/v1/meetings')
        ->assertOk();

    expect($this->userA->fresh()->current_organization_id)->toBe($this->orgA->id);
});

test('a user with no organization gets a clear code rather than an empty list', function () {
    $orphan = User::factory()->create(['current_organization_id' => null]);

    $this->actingAs($orphan, 'sanctum')
        ->getJson('/api/mobile/v1/meetings')
        ->assertStatus(409)
        ->assertJsonPath('code', 'NO_ORGANIZATION_CONTEXT');
});

test('a suspended organization is blocked with json, not an html error page', function () {
    $this->orgA->update(['is_suspended' => true, 'suspended_reason' => 'Payment overdue']);

    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/mobile/v1/meetings')
        ->assertStatus(403)
        ->assertJsonPath('code', 'ORGANIZATION_SUSPENDED');
});
