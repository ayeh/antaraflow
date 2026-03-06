<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Account\Models\AuditLog;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GovernanceAnalyticsService
{
    /**
     * @return array{
     *   total_cost: float,
     *   avg_cost_per_meeting: float,
     *   meeting_count: int
     * }
     */
    public function getMeetingCostEstimate(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        float $hourlyRate = 50.0,
    ): array {
        $meetings = $this->baseMeetingQuery($organizationId, $startDate, $endDate)
            ->whereNotNull('duration_minutes')
            ->withCount('attendees')
            ->get(['id', 'duration_minutes']);

        $totalCost = 0.0;

        foreach ($meetings as $meeting) {
            $hours = $meeting->duration_minutes / 60;
            $totalCost += $hours * $hourlyRate * $meeting->attendees_count;
        }

        $meetingCount = $meetings->count();

        return [
            'total_cost' => round($totalCost, 2),
            'avg_cost_per_meeting' => $meetingCount > 0 ? round($totalCost / $meetingCount, 2) : 0.0,
            'meeting_count' => $meetingCount,
        ];
    }

    /**
     * @return array<int, array{month: string, present: int, total: int, rate: float}>
     */
    public function getAttendanceRateTrends(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $meetings = $this->baseMeetingQuery($organizationId, $startDate, $endDate)
            ->with('attendees')
            ->get(['id', 'meeting_date']);

        $grouped = $meetings
            ->filter(fn ($meeting) => $meeting->meeting_date !== null)
            ->groupBy(fn ($meeting) => $meeting->meeting_date->format('Y-m'));

        $trends = [];

        foreach ($grouped as $month => $monthMeetings) {
            $totalAttendees = 0;
            $presentAttendees = 0;

            foreach ($monthMeetings as $meeting) {
                $totalAttendees += $meeting->attendees->count();
                $presentAttendees += $meeting->attendees->where('is_present', true)->count();
            }

            $trends[] = [
                'month' => Carbon::parse($month.'-01')->format('M Y'),
                'present' => $presentAttendees,
                'total' => $totalAttendees,
                'rate' => $totalAttendees > 0 ? round(($presentAttendees / $totalAttendees) * 100, 2) : 0.0,
            ];
        }

        return $trends;
    }

    /**
     * @return array<int, array{month: string, completed_on_time: int, completed_overdue: int, still_open: int}>
     */
    public function getActionItemCompletionTrends(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $query = ActionItem::query()
            ->where('organization_id', $organizationId);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $items = $query->get(['status', 'due_date', 'completed_at', 'created_at']);

        $grouped = $items->groupBy(fn ($item) => $item->created_at->format('Y-m'));

        $trends = [];

        foreach ($grouped as $month => $monthItems) {
            $completedOnTime = 0;
            $completedOverdue = 0;
            $stillOpen = 0;

            foreach ($monthItems as $item) {
                if ($item->status === ActionItemStatus::Completed) {
                    if ($item->due_date && $item->completed_at && $item->completed_at->gt($item->due_date)) {
                        $completedOverdue++;
                    } else {
                        $completedOnTime++;
                    }
                } elseif (! in_array($item->status, [ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])) {
                    $stillOpen++;
                }
            }

            $trends[] = [
                'month' => Carbon::parse($month.'-01')->format('M Y'),
                'completed_on_time' => $completedOnTime,
                'completed_overdue' => $completedOverdue,
                'still_open' => $stillOpen,
            ];
        }

        return $trends;
    }

    /**
     * @return array<string, int>
     */
    public function getMeetingTypeDistribution(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $meetings = $this->baseMeetingQuery($organizationId, $startDate, $endDate)
            ->get(['meeting_type']);

        return $meetings
            ->groupBy(fn ($meeting) => $meeting->meeting_type ?? 'unspecified')
            ->map(fn (Collection $group) => $group->count())
            ->toArray();
    }

    /**
     * @return array{
     *   avg_days: float,
     *   min_days: float,
     *   max_days: float,
     *   count: int
     * }
     */
    public function getApprovalTurnaround(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $meetingIds = $this->baseMeetingQuery($organizationId, $startDate, $endDate)
            ->pluck('id');

        $finalizedLogs = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('auditable_type', MinutesOfMeeting::class)
            ->whereIn('auditable_id', $meetingIds)
            ->where('action', 'finalized')
            ->get(['auditable_id', 'created_at'])
            ->keyBy('auditable_id');

        $approvedLogs = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('auditable_type', MinutesOfMeeting::class)
            ->whereIn('auditable_id', $meetingIds)
            ->where('action', 'approved')
            ->get(['auditable_id', 'created_at'])
            ->keyBy('auditable_id');

        $turnaroundDays = [];

        foreach ($approvedLogs as $meetingId => $approvedLog) {
            if (isset($finalizedLogs[$meetingId])) {
                $days = $finalizedLogs[$meetingId]->created_at->diffInDays($approvedLog->created_at);
                $turnaroundDays[] = (float) $days;
            }
        }

        $count = count($turnaroundDays);

        return [
            'avg_days' => $count > 0 ? round(array_sum($turnaroundDays) / $count, 2) : 0.0,
            'min_days' => $count > 0 ? min($turnaroundDays) : 0.0,
            'max_days' => $count > 0 ? max($turnaroundDays) : 0.0,
            'count' => $count,
        ];
    }

    /**
     * @return array{
     *   approved_percentage: float,
     *   action_items_assigned_percentage: float,
     *   on_time_completion_percentage: float,
     *   overall_score: float
     * }
     */
    public function getComplianceScore(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $meetings = $this->baseMeetingQuery($organizationId, $startDate, $endDate)
            ->get(['id', 'status']);

        $totalMeetings = $meetings->count();

        $approvedCount = $meetings->where('status', MeetingStatus::Approved)->count();
        $approvedPercentage = $totalMeetings > 0
            ? round(($approvedCount / $totalMeetings) * 100, 2)
            : 0.0;

        $actionItemQuery = ActionItem::query()
            ->where('organization_id', $organizationId);

        if ($startDate && $endDate) {
            $actionItemQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $actionItems = $actionItemQuery->get(['assigned_to', 'status', 'due_date', 'completed_at']);

        $totalItems = $actionItems->count();
        $assignedItems = $actionItems->whereNotNull('assigned_to')->count();
        $assignedPercentage = $totalItems > 0
            ? round(($assignedItems / $totalItems) * 100, 2)
            : 0.0;

        $completedItems = $actionItems->where('status', ActionItemStatus::Completed);
        $totalCompleted = $completedItems->count();
        $onTimeCompleted = $completedItems->filter(function ($item) {
            if (! $item->due_date || ! $item->completed_at) {
                return true;
            }

            return $item->completed_at->lte($item->due_date);
        })->count();

        $onTimePercentage = $totalCompleted > 0
            ? round(($onTimeCompleted / $totalCompleted) * 100, 2)
            : 0.0;

        $overall = $totalMeetings > 0 || $totalItems > 0
            ? round(($approvedPercentage + $assignedPercentage + $onTimePercentage) / 3, 2)
            : 0.0;

        return [
            'approved_percentage' => $approvedPercentage,
            'action_items_assigned_percentage' => $assignedPercentage,
            'on_time_completion_percentage' => $onTimePercentage,
            'overall_score' => $overall,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetrics(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        float $hourlyRate = 50.0,
    ): array {
        return [
            'cost_estimate' => $this->getMeetingCostEstimate($organizationId, $startDate, $endDate, $hourlyRate),
            'attendance_trends' => $this->getAttendanceRateTrends($organizationId, $startDate, $endDate),
            'action_item_trends' => $this->getActionItemCompletionTrends($organizationId, $startDate, $endDate),
            'meeting_type_distribution' => $this->getMeetingTypeDistribution($organizationId, $startDate, $endDate),
            'approval_turnaround' => $this->getApprovalTurnaround($organizationId, $startDate, $endDate),
            'compliance_score' => $this->getComplianceScore($organizationId, $startDate, $endDate),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<MinutesOfMeeting>
     */
    private function baseMeetingQuery(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = MinutesOfMeeting::query()
            ->where('organization_id', $organizationId);

        if ($startDate && $endDate) {
            $query->whereBetween('meeting_date', [$startDate, $endDate]);
        }

        return $query;
    }
}
