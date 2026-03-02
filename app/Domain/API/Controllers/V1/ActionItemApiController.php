<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Requests\V1\StoreApiActionItemRequest;
use App\Domain\API\Requests\V1\UpdateApiActionItemRequest;
use App\Domain\API\Resources\ActionItemResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemPriority;
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

    public function store(StoreApiActionItemRequest $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $data = $request->validated();

        $meetingExists = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->where('id', $data['minutes_of_meeting_id'])
            ->exists();

        if (! $meetingExists) {
            return response()->json([
                'message' => 'Meeting not found or does not belong to this organization.',
            ], 422);
        }

        $data['organization_id'] = $orgId;
        $data['status'] = ActionItemStatus::Open;
        $data['priority'] = isset($data['priority'])
            ? ActionItemPriority::from($data['priority'])
            : ActionItemPriority::Medium;

        // API key auth has no user. Use org owner as fallback, then first member.
        $org = Organization::findOrFail($orgId);
        $owner = $org->members()->wherePivot('role', 'owner')->first()
            ?? $org->members()->first();

        if ($owner === null) {
            return response()->json(['message' => 'Organization has no members.'], 422);
        }

        $data['created_by'] = $owner->id;

        $actionItem = ActionItem::query()->create($data);

        return response()->json(new ActionItemResource($actionItem), 201);
    }

    public function update(UpdateApiActionItemRequest $request, int $id): JsonResponse
    {
        $actionItem = ActionItem::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $actionItem->update($request->validated());

        return response()->json(new ActionItemResource($actionItem->fresh()));
    }
}
