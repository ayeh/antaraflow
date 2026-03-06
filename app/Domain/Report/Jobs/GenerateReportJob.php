<?php

declare(strict_types=1);

namespace App\Domain\Report\Jobs;

use App\Domain\Report\Mail\ReportReadyMail;
use App\Domain\Report\Models\ReportTemplate;
use App\Domain\Report\Services\ReportGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ReportTemplate $template,
    ) {}

    public function handle(ReportGeneratorService $service): void
    {
        $generatedReport = $service->generate($this->template);

        $recipients = $this->template->recipients ?? [];

        foreach ($recipients as $email) {
            Mail::to($email)->send(new ReportReadyMail(
                reportName: $this->template->name,
                reportType: $this->template->type->label(),
                generatedReport: $generatedReport,
            ));
        }
    }
}
