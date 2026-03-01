<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Collaboration\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CommentService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function addComment(Model $commentable, User $user, string $body, ?int $parentId): Comment
    {
        $comment = Comment::query()->create([
            'organization_id' => $user->current_organization_id,
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
            'user_id' => $user->id,
            'body' => $body,
            'parent_id' => $parentId,
        ]);

        $this->auditService->log('created', $comment);

        return $comment->fresh();
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);
        $this->auditService->log('updated', $comment);

        return $comment->fresh();
    }

    public function deleteComment(Comment $comment): void
    {
        $this->auditService->log('deleted', $comment);
        $comment->delete();
    }

    /** @return Collection<int, Comment> */
    public function getComments(Model $commentable): Collection
    {
        return Comment::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
