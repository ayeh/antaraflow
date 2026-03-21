<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SpeakerController extends Controller
{
    use AuthorizesRequests;

    public function update(Request $request, MinutesOfMeeting $meeting, AudioTranscription $transcription): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'old_speaker' => ['required', 'string', 'max:100'],
            'new_speaker' => ['required', 'string', 'max:100'],
        ]);

        $transcription->segments()
            ->where('speaker', $validated['old_speaker'])
            ->update(['speaker' => $validated['new_speaker'], 'is_edited' => true]);

        return response()->json(['message' => 'Speaker renamed successfully.']);
    }
}
