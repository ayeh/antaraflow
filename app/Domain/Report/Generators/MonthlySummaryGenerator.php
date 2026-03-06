<?php

declare(strict_types=1);

namespace App\Domain\Report\Generators;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Report\Models\ReportTemplate;
use App\Support\Enums\ActionItemStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MonthlySummaryGenerator
{
    public function generate(ReportTemplate $template): string
    {
        $filters = $template->filters ?? [];
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : now()->subMonth()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : now()->subMonth()->endOfMonth();

        $meetings = MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->with(['attendees', 'actionItems', 'createdBy'])
            ->get();

        $totalMeetings = $meetings->count();
        $totalDurationMinutes = $meetings->sum('duration_minutes');
        $uniqueAttendees = $meetings->flatMap(fn ($m) => $m->attendees)->unique('user_id')->count();

        $actionItemsCreated = ActionItem::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $actionItemsCompleted = ActionItem::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->where('status', ActionItemStatus::Completed)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        $data = [
            'template' => $template,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalMeetings' => $totalMeetings,
            'totalDurationMinutes' => $totalDurationMinutes,
            'uniqueAttendees' => $uniqueAttendees,
            'actionItemsCreated' => $actionItemsCreated,
            'actionItemsCompleted' => $actionItemsCompleted,
            'meetings' => $meetings,
        ];

        $pdf = Pdf::loadView('reports.pdf.monthly-summary', $data);
        $filename = 'reports/'.$template->organization_id.'/monthly-summary-'.now()->format('Y-m-d-His').'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}
