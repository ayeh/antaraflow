<?php

declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Analytics\Services\AnalyticsEventService;
use App\Domain\Export\Models\MomExport;
use App\Domain\Export\Services\CsvExportService;
use App\Domain\Export\Services\JsonExportService;
use App\Domain\Export\Services\PdfExportService;
use App\Domain\Export\Services\WordExportService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PdfExportService $pdfExportService,
        private WordExportService $wordExportService,
        private CsvExportService $csvExportService,
        private JsonExportService $jsonExportService,
    ) {}

    public function pdf(Request $request, MinutesOfMeeting $meeting): Response
    {
        $this->authorize('view', $meeting);

        $this->applyExportOptions($request, $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'pdf',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'pdf']);

        return $this->pdfExportService->export($meeting);
    }

    public function word(Request $request, MinutesOfMeeting $meeting): StreamedResponse
    {
        $this->authorize('view', $meeting);

        $this->applyExportOptions($request, $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'docx',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'docx']);

        return $this->wordExportService->export($meeting);
    }

    /** Apply optional template and language overrides from the request without persisting. */
    private function applyExportOptions(Request $request, MinutesOfMeeting $meeting): void
    {
        if ($request->filled('template_id')) {
            $meeting->export_template_id = (int) $request->query('template_id');
        }

        if ($request->filled('language') && in_array($request->query('language'), ['ms', 'en'], true)) {
            $meeting->output_language = $request->query('language');
        }
    }

    public function csv(MinutesOfMeeting $meeting): StreamedResponse
    {
        $this->authorize('view', $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'csv',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'csv']);

        return $this->csvExportService->export($meeting);
    }

    public function json(MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'json',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'json']);

        return $this->jsonExportService->export($meeting);
    }
}
