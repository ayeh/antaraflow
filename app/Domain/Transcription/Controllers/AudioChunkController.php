<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Requests\StoreAudioChunkRequest;
use App\Domain\Transcription\Services\AudioStorageService;
use App\Domain\Transcription\Services\TranscriptionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AudioChunkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AudioStorageService $audioStorageService,
        private TranscriptionService $transcriptionService,
    ) {}

    public function store(StoreAudioChunkRequest $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $this->audioStorageService->storeChunk(
            $request->file('chunk'),
            $meeting->organization_id,
            $request->validated('session_id'),
            $request->validated('chunk_index'),
        );

        return response()->json([
            'message' => 'Chunk uploaded.',
            'chunk_index' => $request->validated('chunk_index'),
        ]);
    }

    public function finalize(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'uuid'],
            'mime_type' => ['required', 'string'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        $mergedPath = $this->audioStorageService->mergeChunks(
            $meeting->organization_id,
            $validated['session_id'],
            $validated['mime_type'],
        );

        $transcription = $this->transcriptionService->createFromBrowserRecording(
            $mergedPath,
            $meeting,
            $request->user(),
            $validated['mime_type'],
            $validated['duration_seconds'],
            $validated['language'] ?? 'en',
        );

        return response()->json([
            'message' => 'Recording finalized and transcription started.',
            'transcription' => $transcription,
        ]);
    }

    public function destroy(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'uuid'],
        ]);

        $this->audioStorageService->deleteChunks(
            $meeting->organization_id,
            $validated['session_id'],
        );

        return response()->json(['message' => 'Chunks deleted.']);
    }
}
