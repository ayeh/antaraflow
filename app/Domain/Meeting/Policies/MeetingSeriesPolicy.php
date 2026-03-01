<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Models\User;

class MeetingSeriesPolicy
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

    public function view(User $user, MeetingSeries $meetingSeries): bool
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

    public function update(User $user, MeetingSeries $meetingSeries): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function delete(User $user, MeetingSeries $meetingSeries): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }
}
