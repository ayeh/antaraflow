<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Requests\V1\StoreApiMeetingRequest;
use App\Domain\API\Requests\V1\UpdateApiMeetingRequest;
use App\Domain\API\Resources\MeetingResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $meetings = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => MeetingResource::collection($meetings->items()),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'total' => $meetings->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(new MeetingResource($meeting));
    }

    public function store(StoreApiMeetingRequest $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $data = $request->validated();
        $data['organization_id'] = $orgId;
        $data['status'] = MeetingStatus::Draft;

        $data['created_by'] = $this->resolveCreatedBy($orgId);

        $meeting = MinutesOfMeeting::query()->create($data);

        return response()->json(new MeetingResource($meeting), 201);
    }

    public function update(UpdateApiMeetingRequest $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $meeting->update($request->validated());

        return response()->json(new MeetingResource($meeting->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $meeting->delete();

        return response()->json(null, 204);
    }
}
