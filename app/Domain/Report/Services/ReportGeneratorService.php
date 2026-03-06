<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Report\Generators\ActionItemStatusGenerator;
use App\Domain\Report\Generators\AttendanceReportGenerator;
use App\Domain\Report\Generators\GovernanceComplianceGenerator;
use App\Domain\Report\Generators\MonthlySummaryGenerator;
use App\Domain\Report\Models\GeneratedReport;
use App\Domain\Report\Models\ReportTemplate;
use App\Support\Enums\ReportType;
use Illuminate\Support\Facades\Storage;

class ReportGeneratorService
{
    public function __construct(
        private MonthlySummaryGenerator $monthlySummaryGenerator,
        private ActionItemStatusGenerator $actionItemStatusGenerator,
        private AttendanceReportGenerator $attendanceReportGenerator,
        private GovernanceComplianceGenerator $governanceComplianceGenerator,
    ) {}

    public function generate(ReportTemplate $template): GeneratedReport
    {
        $filePath = match ($template->type) {
            ReportType::MonthlySummary => $this->monthlySummaryGenerator->generate($template),
            ReportType::ActionItemStatus => $this->actionItemStatusGenerator->generate($template),
            ReportType::AttendanceReport => $this->attendanceReportGenerator->generate($template),
            ReportType::GovernanceCompliance => $this->governanceComplianceGenerator->generate($template),
        };

        $fileSize = Storage::disk('local')->exists($filePath)
            ? Storage::disk('local')->size($filePath)
            : null;

        $generatedReport = GeneratedReport::query()->create([
            'report_template_id' => $template->id,
            'organization_id' => $template->organization_id,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'parameters' => $template->filters,
            'generated_at' => now(),
        ]);

        $template->update(['last_generated_at' => now()]);

        return $generatedReport;
    }
}
