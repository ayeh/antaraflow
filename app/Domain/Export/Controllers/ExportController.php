<?php

declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Analytics\Services\AnalyticsEventService;
use App\Domain\Export\Models\MomExport;
use App\Domain\Export\Services\CsvExportService;
use App\Domain\Export\Services\PdfExportService;
use App\Domain\Export\Services\WordExportService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
    ) {}

    public function pdf(MinutesOfMeeting $meeting): Response
    {
        $this->authorize('view', $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'pdf',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'pdf']);

        return $this->pdfExportService->export($meeting);
    }

    public function word(MinutesOfMeeting $meeting): StreamedResponse
    {
        $this->authorize('view', $meeting);

        MomExport::create([
            'minutes_of_meeting_id' => $meeting->id,
            'user_id' => auth()->id(),
            'format' => 'docx',
        ]);

        AnalyticsEventService::track('export.downloaded', $meeting, auth()->user(), ['format' => 'docx']);

        return $this->wordExportService->export($meeting);
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
}
