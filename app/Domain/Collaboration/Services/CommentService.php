<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Collaboration\Jobs\SendMentionNotificationsJob;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomMention;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Events\CommentAdded;
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

        $comment = $comment->refresh();

        if ($commentable instanceof MinutesOfMeeting) {
            CommentAdded::dispatch($comment, (int) $commentable->getKey());
        }

        $this->parseMentions($comment, $commentable, $user);

        return $comment;
    }

    private function parseMentions(Comment $comment, Model $commentable, User $author): void
    {
        preg_match_all('/@([\w-]+)/', $comment->body, $matches);

        if (empty($matches[1])) {
            return;
        }

        $meetingId = ($commentable instanceof MinutesOfMeeting) ? $commentable->id : null;

        foreach ($matches[1] as $handle) {
            $name = str_replace('-', ' ', $handle);
            $mentioned = User::query()
                ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $author->current_organization_id))
                ->where('name', 'like', "%{$name}%")
                ->first();

            if ($mentioned && $mentioned->id !== $author->id && $meetingId !== null) {
                MomMention::create([
                    'comment_id' => $comment->id,
                    'mentioned_user_id' => $mentioned->id,
                    'organization_id' => $author->current_organization_id,
                    'minutes_of_meeting_id' => $meetingId,
                    'is_read' => false,
                ]);
            }
        }

        if (MomMention::where('comment_id', $comment->id)->exists()) {
            SendMentionNotificationsJob::dispatch($comment);
        }
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);
        $this->auditService->log('updated', $comment);

        return $comment->refresh();
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
