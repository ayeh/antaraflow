<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * @return array{
     *   meetings_per_month: array<string, int>,
     *   status_distribution: array<string, int>,
     *   avg_duration_minutes: float
     * }
     */
    public function getMeetingStats(int $orgId, Carbon $startDate, Carbon $endDate): array
    {
        $meetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->get(['meeting_date', 'status', 'duration_minutes']);

        $meetingsPerMonth = $meetings
            ->filter(fn ($meeting) => $meeting->meeting_date !== null)
            ->groupBy(fn ($meeting) => $meeting->meeting_date->format('M Y'))
            ->map(fn ($group) => $group->count())
            ->toArray();

        $statusDistribution = $meetings
            ->groupBy(fn ($meeting) => $meeting->status->value)
            ->map(fn ($group) => $group->count())
            ->toArray();

        $avgDurationMinutes = $meetings
            ->filter(fn ($meeting) => $meeting->duration_minutes !== null)
            ->avg('duration_minutes') ?? 0.0;

        return [
            'meetings_per_month' => $meetingsPerMonth,
            'status_distribution' => $statusDistribution,
            'avg_duration_minutes' => round((float) $avgDurationMinutes, 2),
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   completed: int,
     *   pending: int,
     *   overdue: int,
     *   completion_rate: float
     * }
     */
    public function getActionItemStats(int $orgId, Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = ActionItem::query()
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $total = (clone $baseQuery)->count();

        $completed = (clone $baseQuery)
            ->where('status', ActionItemStatus::Completed)
            ->count();

        $pending = $total - $completed;

        $overdue = (clone $baseQuery)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->where('due_date', '<', now())
            ->whereNotNull('due_date')
            ->count();

        $completionRate = $total > 0
            ? round(($completed / $total) * 100, 2)
            : 0.0;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * @return array{
     *   top_attendees: array<int, array{name: string, count: int}>,
     *   total_attendees: int
     * }
     */
    public function getParticipationStats(int $orgId, Carbon $startDate, Carbon $endDate): array
    {
        $attendees = MomAttendee::query()
            ->whereHas('meeting', fn ($query) => $query
                ->where('organization_id', $orgId)
                ->whereBetween('meeting_date', [$startDate, $endDate])
            )
            ->get(['name', 'email']);

        $topAttendees = $attendees
            ->groupBy('name')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();

        $totalAttendees = $attendees
            ->filter(fn ($attendee) => $attendee->email !== null)
            ->unique('email')
            ->count();

        return [
            'top_attendees' => $topAttendees,
            'total_attendees' => $totalAttendees,
        ];
    }

    /**
     * @return array{
     *   total_meetings_with_ai: int,
     *   total_action_items: int
     * }
     */
    public function getAiUsageStats(int $orgId, Carbon $startDate, Carbon $endDate): array
    {
        $totalMeetingsWithAi = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->whereHas('extractions')
            ->count();

        $totalActionItems = ActionItem::query()
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return [
            'total_meetings_with_ai' => $totalMeetingsWithAi,
            'total_action_items' => $totalActionItems,
        ];
    }
}
