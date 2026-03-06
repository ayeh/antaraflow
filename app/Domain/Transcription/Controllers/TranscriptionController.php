<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Requests\UploadAudioRequest;
use App\Domain\Transcription\Services\AudioStorageService;
use App\Domain\Transcription\Services\TranscriptionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class TranscriptionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private TranscriptionService $transcriptionService,
        private AudioStorageService $audioStorageService,
    ) {}

    public function store(UploadAudioRequest $request, MinutesOfMeeting $meeting): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $meeting);

        $transcription = $this->transcriptionService->upload(
            $request->file('audio'),
            $meeting,
            $request->user(),
            $request->validated('language', 'en'),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Audio uploaded and transcription started.',
                'transcription' => $transcription,
            ]);
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Audio uploaded and transcription started.');
    }

    public function show(MinutesOfMeeting $meeting, AudioTranscription $transcription): View
    {
        $this->authorize('view', $meeting);

        $transcription->load('segments');

        return view('transcriptions.show', compact('meeting', 'transcription'));
    }

    public function destroy(MinutesOfMeeting $meeting, AudioTranscription $transcription): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $meeting);

        $this->audioStorageService->delete($transcription->file_path);

        $meeting->inputs()
            ->where('source_type', AudioTranscription::class)
            ->where('source_id', $transcription->id)
            ->delete();

        $transcription->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Transcription deleted successfully.']);
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Transcription deleted successfully.');
    }
}
