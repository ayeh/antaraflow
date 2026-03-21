<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\TranscriptionResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranscriptionApiController extends ApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $transcriptions = $meeting->transcriptions()->latest()->get();

        return response()->json([
            'data' => TranscriptionResource::collection($transcriptions),
        ]);
    }

    public function show(Request $request, int $id, int $transcriptionId): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()
            ->where('organization_id', $this->organizationId($request))
            ->where('id', $id)
            ->firstOrFail();

        $transcription = AudioTranscription::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->where('id', $transcriptionId)
            ->with('segments')
            ->firstOrFail();

        return response()->json(new TranscriptionResource($transcription));
    }
}
