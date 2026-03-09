<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\UpdateActionItemStatusRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ActionItemStatusController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function update(
        UpdateActionItemStatusRequest $request,
        MinutesOfMeeting $meeting,
        ActionItem $actionItem,
    ): JsonResponse {
        $this->authorize('update', $actionItem);

        $status = ActionItemStatus::from($request->validated('status'));

        $updated = $this->actionItemService->changeStatus(
            $actionItem,
            $status,
            $request->user(),
            $request->validated('comment'),
        );

        return response()->json([
            'id' => $updated->id,
            'status' => $updated->status->value,
            'status_label' => $updated->status->label(),
            'status_color_class' => $updated->status->colorClass(),
            'completed_at' => $updated->completed_at?->toIso8601String(),
        ]);
    }
}
