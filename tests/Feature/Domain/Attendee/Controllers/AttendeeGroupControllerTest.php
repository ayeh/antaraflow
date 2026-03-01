<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('org member can view attendee groups index', function () {
    $group = AttendeeGroup::factory()->for($this->org)->create(['name' => 'Leadership Team']);
    $otherGroup = AttendeeGroup::factory()->create(['name' => 'OtherOrgGroup']);

    $response = $this->actingAs($this->user)->get(route('attendee-groups.index'));

    $response->assertOk();
    $response->assertSee($group->name);
    $response->assertDontSee($otherGroup->name);
});

test('org admin can create attendee group', function () {
    $response = $this->actingAs($this->user)->post(route('attendee-groups.store'), [
        'name' => 'Finance Team',
        'description' => 'All finance members',
        'default_members' => [],
    ]);

    $response->assertRedirect(route('attendee-groups.index'));
    $this->assertDatabaseHas('attendee_groups', [
        'name' => 'Finance Team',
        'organization_id' => $this->org->id,
    ]);
});

test('org admin can create group with members', function () {
    $response = $this->actingAs($this->user)->post(route('attendee-groups.store'), [
        'name' => 'Engineering Team',
        'default_members' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'Lead'],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'Developer'],
        ],
    ]);

    $response->assertRedirect(route('attendee-groups.index'));

    $group = AttendeeGroup::query()->where('name', 'Engineering Team')->first();

    expect($group)->not->toBeNull();
    expect($group->default_members)->toHaveCount(2);
    expect($group->default_members[0]['name'])->toBe('Alice');
    expect($group->default_members[0]['email'])->toBe('alice@example.com');
    expect($group->default_members[1]['name'])->toBe('Bob');
});

test('org admin can update attendee group', function () {
    $group = AttendeeGroup::factory()->for($this->org)->create(['default_members' => []]);

    $response = $this->actingAs($this->user)->put(route('attendee-groups.update', $group), [
        'name' => 'Updated Group Name',
        'description' => 'Updated description',
        'default_members' => [],
    ]);

    $response->assertRedirect(route('attendee-groups.index'));
    $this->assertDatabaseHas('attendee_groups', [
        'id' => $group->id,
        'name' => 'Updated Group Name',
    ]);
});

test('org admin can delete attendee group', function () {
    $group = AttendeeGroup::factory()->for($this->org)->create(['default_members' => []]);

    $response = $this->actingAs($this->user)->delete(route('attendee-groups.destroy', $group));

    $response->assertRedirect(route('attendee-groups.index'));
    $this->assertDatabaseMissing('attendee_groups', ['id' => $group->id]);
});

test('viewer cannot create attendee group', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('attendee-groups.store'), [
        'name' => 'Should Fail',
    ]);

    $response->assertForbidden();
});
