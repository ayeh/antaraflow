<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\TranscriptionResource;
use App\Domain\API\Resources\Mobile\TranscriptionSegmentResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranscriptionController extends MobileController
{
    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'data' => TranscriptionResource::collection($meeting->transcriptions()->latest()->get()),
        ]);
    }

    public function show(Request $request, AudioTranscription $transcription): JsonResponse
    {
        $meeting = $this->meetingFor($transcription);
        $this->authorize('view', $meeting);

        return response()->json(new TranscriptionResource($transcription));
    }

    /**
     * Segments are paged rather than embedded: a two-hour meeting runs to
     * thousands of them, which is far more than a phone should download to show
     * the first screen.
     */
    public function segments(Request $request, AudioTranscription $transcription): JsonResponse
    {
        $meeting = $this->meetingFor($transcription);
        $this->authorize('view', $meeting);

        $limit = min(max($request->integer('limit', 200), 1), 500);

        $segments = $transcription->segments()
            ->orderBy('sequence_order')
            ->cursorPaginate($limit, ['*'], 'cursor', $request->string('cursor')->toString() ?: null);

        return response()->json([
            'data' => TranscriptionSegmentResource::collection($segments->getCollection()),
            'meta' => [
                'next_cursor' => $segments->nextCursor()?->encode(),
                'has_more' => $segments->hasMorePages(),
            ],
        ]);
    }

    public function renameSpeaker(Request $request, AudioTranscription $transcription): JsonResponse
    {
        $meeting = $this->meetingFor($transcription);
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
        ]);

        $updated = $transcription->segments()
            ->where('speaker', $validated['from'])
            ->update(['speaker' => $validated['to'], 'is_edited' => true]);

        return response()->json(['renamed_segments' => $updated]);
    }

    private function meetingFor(AudioTranscription $transcription): MinutesOfMeeting
    {
        $meeting = MinutesOfMeeting::query()->find($transcription->minutes_of_meeting_id);

        abort_if($meeting === null, 404);

        return $meeting;
    }
}
