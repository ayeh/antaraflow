<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomReaction;
use App\Domain\Collaboration\Requests\ToggleReactionRequest;
use Illuminate\Http\JsonResponse;

class ReactionController
{
    public function toggle(ToggleReactionRequest $request, Comment $comment): JsonResponse
    {
        abort_unless(
            $comment->organization_id === $request->user()->current_organization_id,
            403
        );

        $emoji = $request->validated('emoji');
        $userId = $request->user()->id;

        $existing = MomReaction::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            MomReaction::create([
                'comment_id' => $comment->id,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            $action = 'added';
        }

        $count = MomReaction::query()
            ->where('comment_id', $comment->id)
            ->where('emoji', $emoji)
            ->count();

        return response()->json(['action' => $action, 'emoji' => $emoji, 'count' => $count]);
    }
}
