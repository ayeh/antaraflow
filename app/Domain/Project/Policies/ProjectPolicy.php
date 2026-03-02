<?php

declare(strict_types=1);

namespace App\Domain\Project\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Project\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'view_meeting',
        );
    }

    public function view(User $user, Project $project): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'view_meeting',
        );
    }

    public function create(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'create_meeting',
        );
    }

    public function update(User $user, Project $project): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'delete_meeting',
        );
    }
}
