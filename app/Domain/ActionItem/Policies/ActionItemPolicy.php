<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\ActionItem\Models\ActionItem;
use App\Models\User;

class ActionItemPolicy
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

    public function view(User $user, ActionItem $actionItem): bool
    {
        if ($actionItem->organization_id !== $user->current_organization_id) {
            return false;
        }

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
            'edit_meeting',
        );
    }

    public function update(User $user, ActionItem $actionItem): bool
    {
        if ($actionItem->organization_id !== $user->current_organization_id) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }

    public function delete(User $user, ActionItem $actionItem): bool
    {
        if ($actionItem->organization_id !== $user->current_organization_id) {
            return false;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'delete_meeting',
        );
    }
}
