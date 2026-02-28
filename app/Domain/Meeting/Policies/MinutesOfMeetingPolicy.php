<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;

class MinutesOfMeetingPolicy
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

    public function view(User $user, MinutesOfMeeting $meeting): bool
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

    public function update(User $user, MinutesOfMeeting $meeting): bool
    {
        if ($meeting->status === MeetingStatus::Approved) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function delete(User $user, MinutesOfMeeting $meeting): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'delete_meeting',
        );
    }

    public function finalize(User $user, MinutesOfMeeting $meeting): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function approve(User $user, MinutesOfMeeting $meeting): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        ) && $this->authorizationService->isAtLeast(
            $user,
            $user->currentOrganization,
            UserRole::Manager,
        );
    }
}
