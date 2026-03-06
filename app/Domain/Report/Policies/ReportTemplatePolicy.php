<?php

declare(strict_types=1);

namespace App\Domain\Report\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Report\Models\ReportTemplate;
use App\Models\User;

class ReportTemplatePolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }

    public function view(User $user, ReportTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }

    public function create(User $user): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }

    public function update(User $user, ReportTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }

    public function delete(User $user, ReportTemplate $template): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }
}
