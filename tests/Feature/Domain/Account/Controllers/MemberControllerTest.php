<?php

declare(strict_types=1);

use App\Domain\Account\Mail\OrganizationInvitationMail;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Organization}
 */
function ownerOfOrganization(UserRole $role = UserRole::Owner): array
{
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_organization_id' => $org->id]);

    return [$user, $org];
}

test('owner can invite a member by email', function () {
    Mail::fake();
    [$owner, $org] = ownerOfOrganization();

    $response = $this->actingAs($owner)->post(route('organizations.members.store', $org), [
        'email' => 'new@example.com',
        'role' => UserRole::Member->value,
    ]);

    $response->assertRedirect(route('organizations.members.index', $org));
    $this->assertDatabaseHas('organization_invitations', [
        'organization_id' => $org->id,
        'email' => 'new@example.com',
        'role' => UserRole::Member->value,
    ]);
    Mail::assertQueued(OrganizationInvitationMail::class);
});

test('inviting an existing member is rejected', function () {
    [$owner, $org] = ownerOfOrganization();
    $existing = User::factory()->create(['email' => 'member@example.com']);
    $org->members()->attach($existing, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($owner)->post(route('organizations.members.store', $org), [
        'email' => 'member@example.com',
        'role' => UserRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('organization_invitations', ['email' => 'member@example.com']);
});

test('duplicate pending invitation is rejected', function () {
    [$owner, $org] = ownerOfOrganization();
    OrganizationInvitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'pending@example.com',
    ]);

    $response = $this->actingAs($owner)->post(route('organizations.members.store', $org), [
        'email' => 'pending@example.com',
        'role' => UserRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
});

test('member without manage permission cannot invite', function () {
    Mail::fake();
    [$viewer, $org] = ownerOfOrganization(UserRole::Viewer);

    $response = $this->actingAs($viewer)->post(route('organizations.members.store', $org), [
        'email' => 'new@example.com',
        'role' => UserRole::Member->value,
    ]);

    $response->assertForbidden();
    Mail::assertNothingQueued();
});

test('owner can change a member role', function () {
    [$owner, $org] = ownerOfOrganization();
    $member = User::factory()->create();
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($owner)->patch(route('members.update', $member), [
        'role' => UserRole::Manager->value,
    ]);

    $response->assertRedirect();
    expect($org->members()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(UserRole::Manager->value);
});

test('owner can remove a member', function () {
    [$owner, $org] = ownerOfOrganization();
    $member = User::factory()->create();
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($owner)->delete(route('members.destroy', $member));

    $response->assertRedirect();
    $this->assertDatabaseMissing('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $member->id,
    ]);
});

test('the last owner cannot be removed', function () {
    [$owner, $org] = ownerOfOrganization();

    $response = $this->actingAs($owner)->delete(route('members.destroy', $owner));

    $response->assertSessionHasErrors('member');
    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $owner->id,
    ]);
});

test('the last owner cannot be demoted', function () {
    [$owner, $org] = ownerOfOrganization();

    $response = $this->actingAs($owner)->patch(route('members.update', $owner), [
        'role' => UserRole::Admin->value,
    ]);

    $response->assertSessionHasErrors('role');
    expect($org->members()->where('user_id', $owner->id)->first()->pivot->role)
        ->toBe(UserRole::Owner->value);
});

test('owner can revoke a pending invitation', function () {
    [$owner, $org] = ownerOfOrganization();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $org->id]);

    $response = $this->actingAs($owner)->delete(route('organizations.invitations.destroy', [$org, $invitation]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('organization_invitations', ['id' => $invitation->id]);
});
