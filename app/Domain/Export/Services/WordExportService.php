<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WordExportService
{
    public function export(MinutesOfMeeting $meeting): StreamedResponse
    {
        $meeting->load(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes']);

        $phpWord = new PhpWord;
        $phpWord->getDefaultFontName('Arial');

        $section = $phpWord->addSection();

        // Title
        $titleStyle = ['bold' => true, 'size' => 18, 'color' => '1e1b4b'];
        $section->addText($meeting->title, $titleStyle);

        // Meeting info
        $infoStyle = ['size' => 10, 'color' => '6b7280'];
        if ($meeting->meeting_date) {
            $section->addText('Date: '.$meeting->meeting_date->format('F j, Y g:i A'), $infoStyle);
        }
        if ($meeting->location) {
            $section->addText('Location: '.$meeting->location, $infoStyle);
        }
        $section->addText('Status: '.ucfirst($meeting->status->value), $infoStyle);
        $section->addTextBreak();

        // Attendees
        $headingStyle = ['bold' => true, 'size' => 13, 'color' => '4c1d95'];
        $section->addText('Attendees', $headingStyle);

        foreach ($meeting->attendees as $attendee) {
            $name = $attendee->user?->name ?? $attendee->name ?? 'Unknown';
            $section->addText('• '.$name);
        }
        $section->addTextBreak();

        // Action Items
        $section->addText('Action Items', $headingStyle);
        foreach ($meeting->actionItems as $item) {
            $assignee = $item->assignedTo?->name ?? 'Unassigned';
            $section->addText("• {$item->title} — {$assignee} [{$item->status->value}]");
        }

        $filename = "meeting-{$meeting->id}.docx";

        return response()->streamDownload(function () use ($phpWord) {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
