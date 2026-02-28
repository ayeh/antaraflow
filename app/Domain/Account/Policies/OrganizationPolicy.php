<?php

declare(strict_types=1);

namespace App\Domain\Account\Policies;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;

class OrganizationPolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->authorizationService->getUserRole($user, $organization) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->authorizationService->hasPermission($user, $organization, 'manage_organization');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->authorizationService->hasPermission($user, $organization, 'manage_organization');
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->authorizationService->hasPermission($user, $organization, 'manage_members');
    }

    public function manageSettings(User $user, Organization $organization): bool
    {
        return $this->authorizationService->hasPermission($user, $organization, 'manage_settings');
    }
}
