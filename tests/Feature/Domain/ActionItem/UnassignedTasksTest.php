<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->other = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->org->members()->attach($this->other, ['role' => UserRole::Member->value]);

    $this->mine = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'A sitting I kept',
        'created_by' => $this->user->id,
    ]);

    $this->theirs = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'A sitting I was not in',
        'created_by' => $this->other->id,
    ]);
});

function unowned(MinutesOfMeeting $meeting, string $title, int $creator): ActionItem
{
    return ActionItem::createForOrganization($meeting->organization_id, [
        'minutes_of_meeting_id' => $meeting->id,
        'title' => $title,
        'created_by' => $creator,
        'assigned_to' => null,
        'status' => 'open',
    ]);
}

test('an unowned item from my own sitting is listed', function () {
    unowned($this->mine, 'Laksanakan projek AKB', $this->user->id);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Laksanakan projek AKB');
});

test('an unowned item from a sitting I was not in is somebody else\'s problem', function () {
    unowned($this->theirs, 'Not mine to chase', $this->other->id);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('attending the sitting is enough, not just keeping it', function () {
    $this->theirs->attendees()->create([
        'user_id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
    ]);

    unowned($this->theirs, 'I was in the room', $this->other->id);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an item that already has an owner is not in the unowned list', function () {
    ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->mine->id,
        'title' => 'Already mine',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
        'status' => 'open',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a closed unowned item stays closed', function () {
    $item = unowned($this->mine, 'Done long ago', $this->user->id);
    $item->update(['status' => 'completed']);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the personal list is unchanged by any of this', function () {
    unowned($this->mine, 'Nobody owns this', $this->user->id);

    ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->mine->id,
        'title' => 'Mine',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
        'status' => 'open',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Mine');
});
