<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Collaboration\Models\Comment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClientVisibilityController extends Controller
{
    use AuthorizesRequests;

    public function toggleActionItem(Request $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('update', $actionItem);

        $actionItem->update(['client_visible' => ! $actionItem->client_visible]);

        return response()->json(['client_visible' => $actionItem->client_visible]);
    }

    public function toggleComment(Request $request, Comment $comment): JsonResponse
    {
        abort_unless(
            $comment->organization_id === $request->user()->current_organization_id,
            403,
        );

        $comment->update(['client_visible' => ! $comment->client_visible]);

        return response()->json(['client_visible' => $comment->client_visible]);
    }
}
