<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomDocument;
use App\Domain\Meeting\Requests\UploadDocumentRequest;
use App\Support\Enums\InputType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function store(UploadDocumentRequest $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $file = $request->file('document');
        $path = $file->store("meetings/{$meeting->id}/documents", 'local');

        $document = $meeting->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
        ]);

        $meeting->inputs()->create([
            'type' => InputType::Document,
            'source_type' => MomDocument::class,
            'source_id' => $document->id,
        ]);

        return response()->json([
            'document' => $document,
            'message' => 'Document uploaded successfully.',
        ]);
    }

    public function destroy(MinutesOfMeeting $meeting, MomDocument $document): JsonResponse
    {
        $this->authorize('update', $meeting);

        Storage::disk('local')->delete($document->file_path);

        $meeting->inputs()
            ->where('source_type', MomDocument::class)
            ->where('source_id', $document->id)
            ->delete();

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }
}
