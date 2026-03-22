<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\TranscribeVoiceNoteJob;
use App\Domain\Transcription\Models\VoiceNote;
use App\Domain\Transcription\Services\AudioStorageService;
use App\Support\Enums\InputType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VoiceNoteController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AudioStorageService $audioStorage,
    ) {}

    public function index(MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $notes = $meeting->voiceNotes()
            ->with('createdBy')
            ->latest()
            ->get()
            ->map(fn (VoiceNote $note) => [
                'id' => $note->id,
                'transcript' => $note->transcript,
                'status' => $note->status,
                'duration_seconds' => $note->duration_seconds,
                'created_by' => $note->createdBy?->name,
                'created_at' => $note->created_at->diffForHumans(),
                'created_at_iso' => $note->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/mp4'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        $path = $this->audioStorage->store(
            $request->file('audio'),
            $meeting->organization_id,
        );

        $voiceNote = VoiceNote::query()->create([
            'minutes_of_meeting_id' => $meeting->id,
            'organization_id' => $meeting->organization_id,
            'created_by' => $request->user()->id,
            'file_path' => $path,
            'mime_type' => $request->file('audio')->getMimeType() ?? 'audio/webm',
            'file_size' => $request->file('audio')->getSize(),
            'duration_seconds' => $validated['duration_seconds'],
            'status' => 'pending',
        ]);

        $meeting->inputs()->create([
            'type' => InputType::VoiceNote,
            'source_type' => VoiceNote::class,
            'source_id' => $voiceNote->id,
        ]);

        TranscribeVoiceNoteJob::dispatch($voiceNote);

        return response()->json([
            'message' => 'Voice note saved. Transcription in progress.',
            'id' => $voiceNote->id,
        ], 201);
    }

    public function destroy(Request $request, MinutesOfMeeting $meeting, VoiceNote $voiceNote): JsonResponse
    {
        $this->authorize('update', $meeting);

        $voiceNote->delete();

        return response()->json(['message' => 'Voice note deleted.']);
    }
}
