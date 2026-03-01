<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MomTag;
use App\Models\User;

class MomTagPolicy
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

    public function create(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function update(User $user, MomTag $tag): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function delete(User $user, MomTag $tag): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }
}
