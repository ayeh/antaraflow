<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\Export\Models\MomExport;
use App\Domain\Export\Services\PdfExportService;
use App\Domain\Export\Services\WordExportService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The phone never renders a document itself — it asks for one and hands the
 * result to the share sheet.
 */
class ExportController extends MobileController
{
    public function __construct(
        private readonly PdfExportService $pdfExportService,
        private readonly WordExportService $wordExportService,
    ) {}

    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $exports = $meeting->exports()->latest()->limit(20)->get();

        return response()->json([
            'data' => $exports->map(fn (MomExport $export) => [
                'id' => $export->id,
                'format' => $export->format,
                'created_at' => $export->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function download(Request $request, MinutesOfMeeting $meeting): Response
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'docx'])],
            'template_id' => ['sometimes', 'nullable', 'integer'],
            'language' => ['sometimes', Rule::in(['ms', 'en'])],
        ]);

        // Overrides apply to this render only; they are not saved back onto the
        // meeting, so exporting in another language does not change the record.
        if (isset($validated['template_id'])) {
            $meeting->export_template_id = $validated['template_id'];
        }

        if (isset($validated['language'])) {
            $meeting->output_language = $validated['language'];
        }

        MomExport::query()->create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => $this->user($request)->id,
            'format' => $validated['format'],
        ]);

        return $validated['format'] === 'pdf'
            ? $this->pdfExportService->export($meeting)
            : $this->wordExportService->export($meeting);
    }
}
