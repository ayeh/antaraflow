<?php

declare(strict_types=1);

namespace App\Domain\Report\Controllers;

use App\Domain\Report\Jobs\GenerateReportJob;
use App\Domain\Report\Models\ReportTemplate;
use App\Domain\Report\Requests\CreateReportTemplateRequest;
use App\Domain\Report\Requests\UpdateReportTemplateRequest;
use App\Support\Enums\ReportType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ReportTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', ReportTemplate::class);

        $templates = ReportTemplate::query()
            ->with('createdBy')
            ->latest()
            ->get();

        return view('reports.templates.index', compact('templates'));
    }

    public function create(): View
    {
        $this->authorize('create', ReportTemplate::class);

        $reportTypes = ReportType::cases();

        return view('reports.templates.create', compact('reportTypes'));
    }

    public function store(CreateReportTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', ReportTemplate::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

        ReportTemplate::query()->create($data);

        return redirect()->route('reports.index')
            ->with('success', 'Report template created successfully.');
    }

    public function show(ReportTemplate $report): View
    {
        $this->authorize('view', $report);

        $report->load(['createdBy', 'generatedReports' => fn ($q) => $q->latest('generated_at')->limit(10)]);

        return view('reports.templates.edit', [
            'template' => $report,
            'reportTypes' => ReportType::cases(),
        ]);
    }

    public function edit(ReportTemplate $report): View
    {
        $this->authorize('update', $report);

        return view('reports.templates.edit', [
            'template' => $report,
            'reportTypes' => ReportType::cases(),
        ]);
    }

    public function update(UpdateReportTemplateRequest $request, ReportTemplate $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $report->update($data);

        return redirect()->route('reports.index')
            ->with('success', 'Report template updated successfully.');
    }

    public function destroy(ReportTemplate $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report template deleted.');
    }

    public function generate(ReportTemplate $report): RedirectResponse
    {
        $this->authorize('update', $report);

        GenerateReportJob::dispatch($report);

        return redirect()->route('reports.index')
            ->with('success', 'Report generation has been queued. You will be notified when it is ready.');
    }
}
