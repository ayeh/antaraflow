<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Models\User;

class AttendeeGroupPolicy
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

    public function update(User $user, AttendeeGroup $attendeeGroup): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function delete(User $user, AttendeeGroup $attendeeGroup): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }
}
