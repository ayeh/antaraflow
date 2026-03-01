<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Models\User;

class MeetingSharePolicy
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

    public function view(User $user, MeetingShare $meetingShare): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'view_meeting',
        ) && $user->current_organization_id === $meetingShare->organization_id;
    }

    public function create(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function delete(User $user, MeetingShare $meetingShare): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        ) && $user->current_organization_id === $meetingShare->organization_id;
    }
}
