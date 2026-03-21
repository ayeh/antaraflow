<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Requests\V1\StoreApiCommentRequest;
use App\Domain\API\Requests\V1\UpdateApiCommentRequest;
use App\Domain\API\Resources\CommentResource;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentApiController extends ApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $comments = Comment::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('commentable_type', MinutesOfMeeting::class)
            ->where('commentable_id', $meeting->id)
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'data' => CommentResource::collection($comments),
        ]);
    }

    public function store(StoreApiCommentRequest $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validated();
        $data['organization_id'] = $this->organizationId($request);
        $data['commentable_type'] = MinutesOfMeeting::class;
        $data['commentable_id'] = $meeting->id;
        $data['user_id'] = $this->resolveCreatedBy($this->organizationId($request));

        $comment = Comment::query()->create($data);
        $comment->load('user');

        return response()->json(new CommentResource($comment), 201);
    }

    public function update(UpdateApiCommentRequest $request, int $commentId): JsonResponse
    {
        $comment = Comment::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $commentId)
            ->firstOrFail();

        $comment->update($request->validated());

        return response()->json(new CommentResource($comment->fresh()->load('user')));
    }

    public function destroy(Request $request, int $commentId): JsonResponse
    {
        $comment = Comment::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $commentId)
            ->firstOrFail();

        $comment->delete();

        return response()->json(null, 204);
    }
}
