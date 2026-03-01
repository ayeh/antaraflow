<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Collaboration\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function __construct(
        private AuthorizationService $authorizationService,
    ) {}

    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            && $user->current_organization_id === $comment->organization_id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($user->current_organization_id !== $comment->organization_id) {
            return false;
        }

        if ($user->id === $comment->user_id) {
            return true;
        }

        return $this->authorizationService->hasPermission(
            $user,
            $user->currentOrganization,
            'edit_meeting',
        );
    }
}
