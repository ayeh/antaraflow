<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfExportService
{
    public function export(MinutesOfMeeting $meeting): Response
    {
        $meeting->load(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes']);

        $pdf = Pdf::loadView('exports.meeting-pdf', compact('meeting'));

        return $pdf->download("meeting-{$meeting->id}.pdf");
    }
}
