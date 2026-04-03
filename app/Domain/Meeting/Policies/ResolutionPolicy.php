<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Models\User;

class ResolutionPolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function create(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function update(User $user, MeetingResolution $resolution): bool
    {
        if ($resolution->meeting?->organization_id !== $user->current_organization_id) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function delete(User $user, MeetingResolution $resolution): bool
    {
        if ($resolution->meeting?->organization_id !== $user->current_organization_id) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function vote(User $user, MeetingResolution $resolution): bool
    {
        if ($resolution->meeting?->organization_id !== $user->current_organization_id) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }
}
