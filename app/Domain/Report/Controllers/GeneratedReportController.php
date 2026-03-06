<?php

declare(strict_types=1);

namespace App\Domain\Report\Controllers;

use App\Domain\Report\Models\GeneratedReport;
use App\Domain\Report\Models\ReportTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedReportController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', ReportTemplate::class);

        $reports = GeneratedReport::query()
            ->with('reportTemplate')
            ->latest('generated_at')
            ->paginate(20);

        return view('reports.generated.index', compact('reports'));
    }

    public function download(GeneratedReport $report): StreamedResponse
    {
        $this->authorize('viewAny', ReportTemplate::class);

        abort_unless(Storage::disk('local')->exists($report->file_path), 404, 'Report file not found.');

        $filename = basename($report->file_path);

        return Storage::disk('local')->download($report->file_path, $filename);
    }
}
