<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\PrepBriefResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrepBriefApiController extends ApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $briefs = $meeting->prepBriefs()->latest()->get();

        return response()->json([
            'data' => PrepBriefResource::collection($briefs),
        ]);
    }
}
