<?php

declare(strict_types=1);

namespace App\Domain\Report\Mail;

use App\Domain\Report\Models\GeneratedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportName,
        public string $reportType,
        public GeneratedReport $generatedReport,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Report Ready: {$this->reportName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-ready',
            with: [
                'reportName' => $this->reportName,
                'reportType' => $this->reportType,
                'generatedAt' => $this->generatedReport->generated_at,
                'downloadUrl' => route('reports.generated.download', $this->generatedReport),
            ],
        );
    }
}
