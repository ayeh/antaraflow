<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Models\User;

class WebhookEndpointPolicy
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

    public function view(User $user, WebhookEndpoint $endpoint): bool
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

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'manage_settings',
        );
    }
}
