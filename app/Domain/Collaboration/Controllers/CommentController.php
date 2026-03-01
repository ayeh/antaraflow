<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Requests\CreateCommentRequest;
use App\Domain\Collaboration\Requests\UpdateCommentRequest;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CommentService $commentService,
    ) {}

    public function store(CreateCommentRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validated();

        $this->commentService->addComment(
            $meeting,
            $request->user(),
            $validated['body'],
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        );

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Comment added.');
    }

    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validated();

        $this->commentService->updateComment($comment, $validated['body']);

        return redirect()->back()->with('success', 'Comment updated.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $this->commentService->deleteComment($comment);

        return redirect()->back()->with('success', 'Comment deleted.');
    }
}
