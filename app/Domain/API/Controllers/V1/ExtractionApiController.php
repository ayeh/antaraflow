<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\AI\Models\MomExtraction;
use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\ExtractionResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtractionApiController extends ApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $extractions = MomExtraction::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => ExtractionResource::collection($extractions),
        ]);
    }

    public function show(Request $request, int $id, int $extractionId): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $extraction = MomExtraction::query()
            ->where('id', $extractionId)
            ->where('minutes_of_meeting_id', $meeting->id)
            ->firstOrFail();

        return response()->json(new ExtractionResource($extraction));
    }
}
