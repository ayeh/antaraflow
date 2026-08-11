<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\DocumentResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends MobileController
{
    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'data' => DocumentResource::collection($meeting->documents()->latest()->get()),
        ]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $request->validate([
            'file' => ['required', 'file', 'max:25600'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $file = $request->file('file');
        $path = $file->store("meetings/{$meeting->id}/documents", 'local');

        $document = $meeting->documents()->create([
            'uploaded_by' => $this->user($request)->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json(new DocumentResource($document), 201);
    }

    public function download(Request $request, MomDocument $document): BinaryFileResponse
    {
        $meeting = $document->minutesOfMeeting;

        abort_if($meeting === null, 404);
        $this->authorize('view', $meeting);

        $path = Storage::disk('local')->path($document->file_path);

        abort_if(! file_exists($path), 404);

        return response()->file($path, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function destroy(Request $request, MomDocument $document): JsonResponse
    {
        $meeting = $document->minutesOfMeeting;

        abort_if($meeting === null, 404);
        $this->authorize('update', $meeting);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(null, 204);
    }
}
