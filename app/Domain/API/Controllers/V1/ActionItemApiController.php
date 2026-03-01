<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\ActionItemResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionItemApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = ActionItem::query()
            ->where('organization_id', $orgId);

        if ($request->filled('meeting_id')) {
            $meetingId = $request->integer('meeting_id');
            $meetingExists = MinutesOfMeeting::query()
                ->where('organization_id', $orgId)
                ->where('id', $meetingId)
                ->exists();

            if (! $meetingExists) {
                return response()->json([
                    'data' => [],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
                ]);
            }

            $query->where('minutes_of_meeting_id', $meetingId);
        }

        if ($request->filled('status')) {
            $statusEnum = ActionItemStatus::tryFrom($request->string('status')->toString());

            if ($statusEnum === null) {
                return response()->json([
                    'message' => 'Invalid status value. Accepted values: '.implode(', ', array_column(ActionItemStatus::cases(), 'value')),
                ], 422);
            }

            $query->where('status', $statusEnum->value);
        }

        $actionItems = $query->latest()->paginate(20);

        return response()->json([
            'data' => ActionItemResource::collection($actionItems->items()),
            'meta' => [
                'current_page' => $actionItems->currentPage(),
                'last_page' => $actionItems->lastPage(),
                'total' => $actionItems->total(),
            ],
        ]);
    }
}
