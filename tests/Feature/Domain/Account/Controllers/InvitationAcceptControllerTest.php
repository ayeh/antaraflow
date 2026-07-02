<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('existing user can accept an invitation', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'invited@example.com', 'current_organization_id' => null]);
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'invited@example.com',
        'role' => UserRole::Manager,
    ]);

    $response = $this->actingAs($user)->post(route('invitations.accept', ['token' => $invitation->token]));

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => UserRole::Manager->value,
    ]);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($user->fresh()->current_organization_id)->toBe($org->id);
});

test('a new user can register while accepting an invitation', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    $org = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'brandnew@example.com',
        'role' => UserRole::Member,
    ]);

    $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
        'name' => 'Brand New',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('users', ['email' => 'brandnew@example.com', 'name' => 'Brand New']);

    $user = User::query()->where('email', 'brandnew@example.com')->first();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
    ]);
    expect($user->email_verified_at)->not->toBeNull();
});

test('an expired invitation cannot be accepted', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $org->id,
        'email' => 'invited@example.com',
    ]);

    $response = $this->actingAs($user)->post(route('invitations.accept', ['token' => $invitation->token]));

    $response->assertSessionHasErrors('invitation');
    $this->assertDatabaseMissing('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
    ]);
});

test('a user cannot accept an invitation for a different email', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'someone-else@example.com']);
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $org->id,
        'email' => 'invited@example.com',
    ]);

    $response = $this->actingAs($user)->post(route('invitations.accept', ['token' => $invitation->token]));

    $response->assertSessionHasErrors('invitation');
    $this->assertDatabaseMissing('organization_user', [
        'organization_id' => $org->id,
        'user_id' => $user->id,
    ]);
});

test('the accept page renders for a valid invitation', function () {
    $org = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $org->id]);

    $response = $this->get(route('invitations.accept.show', ['token' => $invitation->token]));

    $response->assertSuccessful();
    $response->assertSee($org->name);
});
