<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userWithTwoOrgs(): array
{
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $user = User::factory()->create(['current_organization_id' => $orgA->id]);
    $orgA->members()->attach($user, ['role' => UserRole::Manager->value]);
    $orgB->members()->attach($user, ['role' => UserRole::Member->value]);

    return [$user, $orgA, $orgB];
}

it('switches to another org the user belongs to', function (): void {
    [$user, $orgA, $orgB] = userWithTwoOrgs();

    $this->actingAs($user)
        ->post(route('organizations.switch', $orgB))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($orgB->id);
});

it('stays on current org when switching to it', function (): void {
    [$user, $orgA] = userWithTwoOrgs();

    $this->actingAs($user)
        ->post(route('organizations.switch', $orgA))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($orgA->id);
});

it('returns 403 when switching to an org the user does not belong to', function (): void {
    $user = User::factory()->create();
    $otherOrg = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.switch', $otherOrg))
        ->assertForbidden();
});

it('requires authentication to switch org', function (): void {
    $org = Organization::factory()->create();

    $this->post(route('organizations.switch', $org))
        ->assertRedirect(route('login'));
});
