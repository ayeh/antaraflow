<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function export(MinutesOfMeeting $meeting): StreamedResponse
    {
        $meeting->load(['actionItems.assignedTo']);

        $filename = "meeting-{$meeting->id}-action-items.csv";

        return response()->streamDownload(function () use ($meeting) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Title', 'Description', 'Assigned To', 'Priority', 'Status', 'Due Date', 'Created At']);

            foreach ($meeting->actionItems as $item) {
                fputcsv($handle, [
                    $item->title,
                    $item->description ?? '',
                    $item->assignedTo?->name ?? 'Unassigned',
                    $item->priority?->value ?? '',
                    $item->status->value,
                    $item->due_date?->format('Y-m-d') ?? '',
                    $item->created_at->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
