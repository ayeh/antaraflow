<?php

declare(strict_types=1);

namespace App\Domain\Report\Generators;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Report\Models\ReportTemplate;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\ExtractionType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MeetingTrendGenerator
{
    public function generate(ReportTemplate $template): string
    {
        $filters = $template->filters ?? [];
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : now()->subMonths(3)->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : now();

        $orgId = $template->organization_id;

        // Meeting frequency by month
        $meetings = MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->with(['attendees', 'actionItems', 'extractions'])
            ->orderBy('meeting_date')
            ->get();

        $meetingsByMonth = $meetings->groupBy(fn ($m) => $m->meeting_date->format('Y-m'));

        // Topic frequency from extractions
        $topicExtractions = MomExtraction::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('type', ExtractionType::Topics)
            ->whereHas('meeting', fn ($q) => $q->whereBetween('meeting_date', [$startDate, $endDate]))
            ->get();

        $topicFrequency = [];
        foreach ($topicExtractions as $extraction) {
            $topics = $extraction->structured_data ?? [];
            foreach ($topics as $topic) {
                $topicText = is_array($topic) ? ($topic['topic'] ?? $topic['title'] ?? '') : (string) $topic;
                $topicText = mb_strtolower(trim($topicText));
                if ($topicText !== '') {
                    $topicFrequency[$topicText] = ($topicFrequency[$topicText] ?? 0) + 1;
                }
            }
        }
        arsort($topicFrequency);
        $topTopics = array_slice($topicFrequency, 0, 15, true);

        // Action item trends
        $actionItems = ActionItem::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $aiByMonth = $actionItems->groupBy(fn ($ai) => $ai->created_at->format('Y-m'));
        $completionRateByMonth = [];
        foreach ($aiByMonth as $month => $items) {
            $total = $items->count();
            $completed = $items->where('status', ActionItemStatus::Completed)->count();
            $completionRateByMonth[$month] = $total > 0 ? round(($completed / $total) * 100) : 0;
        }

        // Assignee performance
        $assigneeStats = $actionItems->groupBy('assigned_to')->map(function ($items, $assignedTo) {
            $total = $items->count();
            $completed = $items->where('status', ActionItemStatus::Completed)->count();
            $overdue = $items->filter(fn ($i) => $i->due_date && $i->due_date->isPast() && ! in_array($i->status, [ActionItemStatus::Completed, ActionItemStatus::Cancelled]))->count();

            return [
                'assigned_to' => $assignedTo,
                'total' => $total,
                'completed' => $completed,
                'overdue' => $overdue,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ];
        })->sortByDesc('total')->take(10)->values();

        // Average attendance rate
        $avgAttendance = $meetings->count() > 0
            ? round($meetings->avg(fn ($m) => $m->attendees->where('is_present', true)->count()), 1)
            : 0;

        // Decisions count
        $decisionExtractions = MomExtraction::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('type', ExtractionType::Decisions)
            ->whereHas('meeting', fn ($q) => $q->whereBetween('meeting_date', [$startDate, $endDate]))
            ->get();

        $totalDecisions = 0;
        foreach ($decisionExtractions as $ext) {
            $data = $ext->structured_data ?? [];
            $totalDecisions += is_array($data) ? count($data) : 0;
        }

        $data = [
            'template' => $template,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'meetings' => $meetings,
            'meetingsByMonth' => $meetingsByMonth,
            'topTopics' => $topTopics,
            'completionRateByMonth' => $completionRateByMonth,
            'assigneeStats' => $assigneeStats,
            'avgAttendance' => $avgAttendance,
            'totalDecisions' => $totalDecisions,
            'totalMeetings' => $meetings->count(),
            'totalActionItems' => $actionItems->count(),
        ];

        $pdf = Pdf::loadView('reports.pdf.meeting-trend', $data);
        $filename = 'reports/'.$orgId.'/meeting-trend-'.now()->format('Y-m-d-His').'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}
