<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\AttendeeResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendeeApiController extends ApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $attendees = $meeting->attendees()->get();

        return response()->json([
            'data' => AttendeeResource::collection($attendees),
        ]);
    }
}
