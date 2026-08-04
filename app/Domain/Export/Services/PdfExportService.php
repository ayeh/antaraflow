<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfExportService
{
    public function __construct(
        private readonly TemplateRenderService $renderer,
        private readonly MomTranslationService $translator,
    ) {}

    public function export(MinutesOfMeeting $meeting): Response
    {
        $pdf = Pdf::loadHTML($this->buildHtml($meeting))->setPaper('A4', 'portrait');

        return $pdf->download("meeting-{$meeting->id}.pdf");
    }

    public function generate(MinutesOfMeeting $meeting): string
    {
        return Pdf::loadHTML($this->buildHtml($meeting))->setPaper('A4', 'portrait')->output();
    }

    private function buildHtml(MinutesOfMeeting $meeting): string
    {
        $template = $this->renderer->resolveTemplate($meeting);
        $language = $meeting->output_language ?? $meeting->language ?? 'ms';

        if ($this->translator->needsTranslation($meeting, $language)) {
            $this->translator->applyTranslations($meeting, $language);
        }

        $html = $this->renderer->renderHtml($meeting, $template, $language);

        if ($meeting->status === MeetingStatus::Approved) {
            $circulation = $meeting->circulations()
                ->where('status', 'closed_approved')
                ->with('recipients')
                ->latest()
                ->first();

            if ($circulation) {
                $confirmationHtml = view('pdf.mom-confirmation-page', [
                    'meeting' => $meeting,
                    'circulation' => $circulation,
                ])->render();

                $html = str_replace('</body>', $confirmationHtml.'</body>', $html);
            }
        }

        return $html;
    }
}
