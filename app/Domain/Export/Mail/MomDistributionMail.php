<?php

declare(strict_types=1);

namespace App\Domain\Export\Mail;

use App\Domain\Export\Models\MomEmailDistribution;
use App\Domain\Export\Services\PdfExportService;
use App\Domain\Export\Services\WordExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MomDistributionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MomEmailDistribution $distribution) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->distribution->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mom-distribution',
            with: ['distribution' => $this->distribution],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $meeting = $this->distribution->meeting;

        if ($this->distribution->export_format === 'pdf') {
            return [
                Attachment::fromData(
                    fn () => app(PdfExportService::class)->generate($meeting),
                    "minutes-{$meeting->id}.pdf",
                )->withMime('application/pdf'),
            ];
        }

        return [
            Attachment::fromData(
                fn () => app(WordExportService::class)->generate($meeting),
                "minutes-{$meeting->id}.docx",
            )->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
