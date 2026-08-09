<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\VoiceNoteResource;
use App\Domain\LiveMeeting\Support\AudioChunkFormats;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\TranscribeVoiceNoteJob;
use App\Domain\Transcription\Models\VoiceNote;
use App\Domain\Transcription\Services\AudioStorageService;
use App\Support\Enums\InputType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VoiceNoteController extends MobileController
{
    public function __construct(
        private readonly AudioStorageService $audioStorage,
    ) {}

    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $notes = $meeting->voiceNotes()->with('createdBy:id,name')->latest()->get();

        return response()->json(['data' => VoiceNoteResource::collection($notes)]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', AudioChunkFormats::MIMETYPES)],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:600'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $file = $request->file('audio');
        $path = $this->audioStorage->store($file, $meeting->organization_id);

        $voiceNote = VoiceNote::createForOrganization($meeting->organization_id, [
            'minutes_of_meeting_id' => $meeting->id,
            'created_by' => $this->user($request)->id,
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'audio/mp4',
            'file_size' => $file->getSize(),
            'duration_seconds' => $validated['duration_seconds'],
            'status' => 'pending',
        ]);

        $meeting->inputs()->create([
            'type' => InputType::VoiceNote,
            'source_type' => VoiceNote::class,
            'source_id' => $voiceNote->id,
        ]);

        TranscribeVoiceNoteJob::dispatch($voiceNote);

        $voiceNote->load('createdBy:id,name');

        return response()->json(new VoiceNoteResource($voiceNote), 201);
    }

    public function destroy(Request $request, VoiceNote $voiceNote): JsonResponse
    {
        $meeting = MinutesOfMeeting::query()->find($voiceNote->minutes_of_meeting_id);

        abort_if($meeting === null, 404);
        $this->authorize('update', $meeting);

        Storage::disk('local')->delete($voiceNote->file_path);
        $voiceNote->delete();

        return response()->json(null, 204);
    }
}
