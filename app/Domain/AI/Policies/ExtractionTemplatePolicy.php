<?php

declare(strict_types=1);

namespace App\Domain\AI\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Models\User;

class ExtractionTemplatePolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function view(User $user, ExtractionTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
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

    public function update(User $user, ExtractionTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }

    public function delete(User $user, ExtractionTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_templates',
        );
    }
}
