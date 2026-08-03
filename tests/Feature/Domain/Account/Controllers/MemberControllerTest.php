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

test('owner can invite another owner', function () {
    Mail::fake();
    [$owner, $org] = ownerOfOrganization();

    $response = $this->actingAs($owner)->post(route('organizations.members.store', $org), [
        'email' => 'coowner@example.com',
        'role' => UserRole::Owner->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('organization_invitations', [
        'email' => 'coowner@example.com',
        'role' => UserRole::Owner->value,
    ]);
});

/**
 * @return array{0: User, 1: Organization, 2: User}
 */
function organizationWithAdmin(): array
{
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($owner, ['role' => UserRole::Owner->value]);
    $org->members()->attach($admin, ['role' => UserRole::Admin->value]);

    return [$admin, $org, $owner];
}

test('admin cannot invite someone as owner or admin', function () {
    Mail::fake();
    [$admin, $org] = organizationWithAdmin();

    foreach ([UserRole::Owner, UserRole::Admin] as $role) {
        $this->actingAs($admin)->post(route('organizations.members.store', $org), [
            'email' => 'new@example.com',
            'role' => $role->value,
        ])->assertSessionHasErrors('role');
    }

    Mail::assertNothingQueued();
    $this->assertDatabaseMissing('organization_invitations', ['email' => 'new@example.com']);
});

test('admin can invite a member with a lower role', function () {
    Mail::fake();
    [$admin, $org] = organizationWithAdmin();

    $response = $this->actingAs($admin)->post(route('organizations.members.store', $org), [
        'email' => 'lower@example.com',
        'role' => UserRole::Manager->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('organization_invitations', [
        'email' => 'lower@example.com',
        'role' => UserRole::Manager->value,
    ]);
});

test('admin cannot promote a member to admin or owner', function () {
    [$admin, $org] = organizationWithAdmin();
    $member = User::factory()->create();
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    foreach ([UserRole::Admin, UserRole::Owner] as $role) {
        $this->actingAs($admin)->patch(route('members.update', $member), [
            'role' => $role->value,
        ])->assertForbidden();
    }

    expect($org->members()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(UserRole::Member->value);
});

test('admin cannot change or remove an owner', function () {
    [$admin, $org, $owner] = organizationWithAdmin();

    $this->actingAs($admin)->patch(route('members.update', $owner), [
        'role' => UserRole::Member->value,
    ])->assertForbidden();

    $this->actingAs($admin)->delete(route('members.destroy', $owner))->assertForbidden();

    $this->assertDatabaseHas('organization_user', [
        'user_id' => $owner->id,
        'role' => UserRole::Owner->value,
    ]);
});

test('admin cannot manage another admin', function () {
    [$admin, $org] = organizationWithAdmin();
    $otherAdmin = User::factory()->create();
    $org->members()->attach($otherAdmin, ['role' => UserRole::Admin->value]);

    $this->actingAs($admin)->patch(route('members.update', $otherAdmin), [
        'role' => UserRole::Member->value,
    ])->assertForbidden();

    $this->actingAs($admin)->delete(route('members.destroy', $otherAdmin))->assertForbidden();
});

test('admin cannot escalate their own role', function () {
    [$admin, $org] = organizationWithAdmin();

    $this->actingAs($admin)->patch(route('members.update', $admin), [
        'role' => UserRole::Owner->value,
    ])->assertForbidden();

    expect($org->members()->where('user_id', $admin->id)->first()->pivot->role)
        ->toBe(UserRole::Admin->value);
});

test('admin can change and remove a lower-ranked member', function () {
    [$admin, $org] = organizationWithAdmin();
    $member = User::factory()->create();
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    $this->actingAs($admin)->patch(route('members.update', $member), [
        'role' => UserRole::Manager->value,
    ])->assertRedirect();

    expect($org->members()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(UserRole::Manager->value);

    $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect();

    $this->assertDatabaseMissing('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $member->id,
    ]);
});

test('the remove-member confirm message escapes a breakout payload in a member name', function () {
    /**
     * A member's display name is self-editable, so it is attacker-controlled
     * input reaching another admin's session. It was interpolated raw into an
     * inline onsubmit handler; @js() is what stops a crafted name from running
     * as JavaScript when the owner clicks Remove.
     */
    [$owner, $org] = ownerOfOrganization();

    $payload = "x'); window.__pwned2 = 1; ('";
    $member = User::factory()->create(['name' => $payload]);
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($owner)
        ->get(route('organizations.members.index', $org))
        ->assertStatus(200);

    $response->assertSee('Remove x\u0027); window.__pwned2 = 1; (\u0027 from this organization?', false)
        ->assertDontSee('Remove x&#039;); window.__pwned2 = 1; (&#039; from this organization?', false);
});

test('the revoke-invitation confirm message escapes a breakout payload in the invitation email', function () {
    /**
     * The invitation email is stored free text. Same inline-onsubmit sink as
     * the remove-member row directly above it in the same view.
     */
    [$owner, $org] = ownerOfOrganization();

    $payload = "x'); window.__pwned2 = 1; ('@example.com";
    OrganizationInvitation::factory()->create([
        'organization_id' => $org->id,
        'email' => $payload,
        'invited_by_user_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->get(route('organizations.members.index', $org))
        ->assertStatus(200);

    $response->assertSee('Revoke the invitation for x\u0027); window.__pwned2 = 1; (\u0027@example.com?', false)
        ->assertDontSee('Revoke the invitation for x&#039;); window.__pwned2 = 1; (&#039;@example.com?', false);
});
