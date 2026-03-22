<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Requests\RenameSpeakerRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SpeakerController extends Controller
{
    use AuthorizesRequests;

    public function update(RenameSpeakerRequest $request, MinutesOfMeeting $meeting, AudioTranscription $transcription): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validated();

        $transcription->segments()
            ->where('speaker', $validated['old_speaker'])
            ->update(['speaker' => $validated['new_speaker'], 'is_edited' => true]);

        return response()->json(['message' => 'Speaker renamed successfully.']);
    }

    public function suggestions(MinutesOfMeeting $meeting, AudioTranscription $transcription): JsonResponse
    {
        $this->authorize('view', $meeting);

        $attendeeNames = $meeting->attendees()
            ->where('is_present', true)
            ->pluck('name')
            ->toArray();

        $currentSpeakers = $transcription->segments()
            ->select('speaker')
            ->distinct()
            ->whereNotNull('speaker')
            ->pluck('speaker')
            ->toArray();

        return response()->json([
            'attendees' => $attendeeNames,
            'current_speakers' => $currentSpeakers,
        ]);
    }
}
