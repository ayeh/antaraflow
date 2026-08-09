<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\CommentResource;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends MobileController
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $comments = Comment::query()
            ->where('commentable_type', MinutesOfMeeting::class)
            ->where('commentable_id', $meeting->id)
            ->with(['user:id,name,avatar_path'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => CommentResource::collection($comments)]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $comment = $this->commentService->addComment(
            $meeting,
            $this->user($request),
            $validated['body'],
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        );

        $comment->load('user:id,name,avatar_path');

        return response()->json(new CommentResource($comment), 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->commentService->updateComment($comment, $validated['body']);

        $comment->refresh()->load('user:id,name,avatar_path');

        return response()->json(new CommentResource($comment));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
