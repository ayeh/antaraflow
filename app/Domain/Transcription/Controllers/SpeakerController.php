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
}
