<?php

declare(strict_types=1);

namespace App\Domain\Report\Generators;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Report\Models\ReportTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceReportGenerator
{
    public function generate(ReportTemplate $template): string
    {
        $filters = $template->filters ?? [];
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : now()->subMonths(3);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : now();

        $meetings = MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->with(['attendees.user'])
            ->get();

        $attendanceByPerson = [];

        foreach ($meetings as $meeting) {
            foreach ($meeting->attendees as $attendee) {
                $personName = $attendee->user?->name ?? $attendee->name ?? 'Unknown';
                $personKey = $attendee->user_id ?? $attendee->email ?? $personName;

                if (! isset($attendanceByPerson[$personKey])) {
                    $attendanceByPerson[$personKey] = [
                        'name' => $personName,
                        'invited' => 0,
                        'present' => 0,
                    ];
                }

                $attendanceByPerson[$personKey]['invited']++;
                if ($attendee->is_present) {
                    $attendanceByPerson[$personKey]['present']++;
                }
            }
        }

        foreach ($attendanceByPerson as &$person) {
            $person['rate'] = $person['invited'] > 0
                ? round(($person['present'] / $person['invited']) * 100, 1)
                : 0;
        }
        unset($person);

        usort($attendanceByPerson, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        $data = [
            'template' => $template,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalMeetings' => $meetings->count(),
            'attendanceByPerson' => $attendanceByPerson,
        ];

        $pdf = Pdf::loadView('reports.pdf.attendance-report', $data);
        $filename = 'reports/'.$template->organization_id.'/attendance-report-'.now()->format('Y-m-d-His').'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}
