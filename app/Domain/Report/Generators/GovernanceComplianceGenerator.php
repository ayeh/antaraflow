<?php

declare(strict_types=1);

namespace App\Domain\Report\Generators;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Report\Models\ReportTemplate;
use App\Support\Enums\MeetingStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class GovernanceComplianceGenerator
{
    public function generate(ReportTemplate $template): string
    {
        $filters = $template->filters ?? [];
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : now()->subMonths(3);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : now();

        $meetings = MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->with(['attendees'])
            ->get();

        $totalMeetings = $meetings->count();
        $finalizedCount = $meetings->where('status', MeetingStatus::Finalized)->count()
            + $meetings->where('status', MeetingStatus::Approved)->count();
        $approvedCount = $meetings->where('status', MeetingStatus::Approved)->count();

        $quorumMeetings = 0;
        foreach ($meetings as $meeting) {
            $totalAttendees = $meeting->attendees->count();
            $presentAttendees = $meeting->attendees->where('is_present', true)->count();
            if ($totalAttendees > 0 && ($presentAttendees / $totalAttendees) >= 0.5) {
                $quorumMeetings++;
            }
        }

        $quorumPercentage = $totalMeetings > 0 ? round(($quorumMeetings / $totalMeetings) * 100, 1) : 0;
        $approvalRate = $finalizedCount > 0 ? round(($approvedCount / $finalizedCount) * 100, 1) : 0;

        $averageApprovalDays = 0;
        $approvedMeetings = $meetings->where('status', MeetingStatus::Approved);
        if ($approvedMeetings->isNotEmpty()) {
            $totalDays = $approvedMeetings->sum(function ($meeting) {
                return $meeting->updated_at->diffInDays($meeting->meeting_date);
            });
            $averageApprovalDays = round($totalDays / $approvedMeetings->count(), 1);
        }

        $data = [
            'template' => $template,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalMeetings' => $totalMeetings,
            'finalizedCount' => $finalizedCount,
            'approvedCount' => $approvedCount,
            'quorumMeetings' => $quorumMeetings,
            'quorumPercentage' => $quorumPercentage,
            'approvalRate' => $approvalRate,
            'averageApprovalDays' => $averageApprovalDays,
        ];

        $pdf = Pdf::loadView('reports.pdf.governance-compliance', $data);
        $filename = 'reports/'.$template->organization_id.'/governance-compliance-'.now()->format('Y-m-d-His').'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}
