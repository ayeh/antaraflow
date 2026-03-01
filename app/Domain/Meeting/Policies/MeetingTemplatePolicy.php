<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Models\User;

class MeetingTemplatePolicy
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

    public function view(User $user, MeetingTemplate $template): bool
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

    public function update(User $user, MeetingTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function delete(User $user, MeetingTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }
}
