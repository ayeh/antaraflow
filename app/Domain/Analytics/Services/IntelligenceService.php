<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class IntelligenceService
{
    /**
     * @return array<int, array{topic: string, count: int, meetings: array}>
     */
    public function getTopicFrequency(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = MomTopic::query()
            ->whereHas('minutesOfMeeting', function (Builder $q) use ($orgId, $start, $end) {
                $q->where('organization_id', $orgId);
                if ($start && $end) {
                    $q->whereBetween('meeting_date', [$start, $end]);
                }
            })
            ->with(['minutesOfMeeting' => fn ($q) => $q->select('id', 'title', 'meeting_date')]);

        $topics = $query->get();

        $grouped = $topics->groupBy(fn (MomTopic $topic) => mb_strtolower(trim($topic->title)));

        $result = [];

        foreach ($grouped as $normalizedTitle => $topicGroup) {
            $meetings = $topicGroup->map(fn (MomTopic $topic) => [
                'id' => $topic->minutesOfMeeting->id,
                'title' => $topic->minutesOfMeeting->title,
                'date' => $topic->minutesOfMeeting->meeting_date?->toDateString(),
            ])->unique('id')->values()->toArray();

            $result[] = [
                'topic' => $topicGroup->first()->title,
                'count' => count($meetings),
                'meetings' => $meetings,
            ];
        }

        usort($result, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * @return array{total_decisions: int, with_action_items: int, without_action_items: int, follow_through_rate: float}
     */
    public function getDecisionFollowThrough(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $meetings = $this->baseMeetingQuery($orgId, $start, $end)
            ->whereHas('extractions', fn (Builder $q) => $q->where('type', 'decisions'))
            ->with([
                'extractions' => fn ($q) => $q->where('type', 'decisions')->latest(),
                'actionItems',
            ])
            ->get();

        $totalDecisions = 0;
        $withActionItems = 0;

        foreach ($meetings as $meeting) {
            $extraction = $meeting->extractions->first();
            if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
                continue;
            }

            foreach ($extraction->structured_data as $decision) {
                $totalDecisions++;
                $decisionText = mb_strtolower($decision['decision'] ?? '');

                if (empty($decisionText)) {
                    continue;
                }

                $hasRelated = $meeting->actionItems->contains(function (ActionItem $item) use ($decisionText) {
                    $itemText = mb_strtolower($item->title.' '.($item->description ?? ''));
                    $keywords = $this->extractKeywords($decisionText);

                    foreach ($keywords as $keyword) {
                        if (str_contains($itemText, $keyword)) {
                            return true;
                        }
                    }

                    return false;
                });

                if ($hasRelated) {
                    $withActionItems++;
                }
            }
        }

        $withoutActionItems = $totalDecisions - $withActionItems;

        return [
            'total_decisions' => $totalDecisions,
            'with_action_items' => $withActionItems,
            'without_action_items' => $withoutActionItems,
            'follow_through_rate' => $totalDecisions > 0
                ? round(($withActionItems / $totalDecisions) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * @return array<int, array{assignee: string, assignee_id: int|null, total: int, completed: int, completion_rate: float, on_time: int, on_time_rate: float, avg_days_to_complete: float}>
     */
    public function getAssigneePerformance(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = ActionItem::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('assigned_to')
            ->with('assignedTo');

        if ($start && $end) {
            $query->whereHas('meeting', function (Builder $q) use ($start, $end) {
                $q->whereBetween('meeting_date', [$start, $end]);
            });
        }

        $items = $query->get();
        $grouped = $items->groupBy('assigned_to');

        $result = [];

        foreach ($grouped as $assigneeId => $assigneeItems) {
            $assignee = $assigneeItems->first()->assignedTo;
            $total = $assigneeItems->count();

            $completed = $assigneeItems->where('status', ActionItemStatus::Completed);
            $completedCount = $completed->count();

            $onTime = $completed->filter(function (ActionItem $item) {
                if (! $item->due_date || ! $item->completed_at) {
                    return true;
                }

                return $item->completed_at->lte($item->due_date);
            });
            $onTimeCount = $onTime->count();

            $daysToComplete = $completed
                ->filter(fn (ActionItem $item) => $item->completed_at !== null)
                ->map(fn (ActionItem $item) => $item->created_at->diffInDays($item->completed_at));

            $avgDays = $daysToComplete->isNotEmpty()
                ? round($daysToComplete->avg(), 1)
                : 0.0;

            $result[] = [
                'assignee' => $assignee?->name ?? 'Unknown',
                'assignee_id' => $assigneeId,
                'total' => $total,
                'completed' => $completedCount,
                'completion_rate' => $total > 0 ? round(($completedCount / $total) * 100, 1) : 0.0,
                'on_time' => $onTimeCount,
                'on_time_rate' => $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100, 1) : 0.0,
                'avg_days_to_complete' => $avgDays,
            ];
        }

        usort($result, fn (array $a, array $b) => $b['completion_rate'] <=> $a['completion_rate']);

        return $result;
    }

    /**
     * Find topics that appear in 3+ meetings without associated decisions.
     *
     * @return array<int, array{topic: string, occurrences: int, meetings: array}>
     */
    public function getUnresolvedPatterns(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $topicFrequency = $this->getTopicFrequency($orgId, $start, $end);

        $meetingsWithDecisions = $this->baseMeetingQuery($orgId, $start, $end)
            ->whereHas('extractions', fn (Builder $q) => $q->where('type', 'decisions'))
            ->with(['extractions' => fn ($q) => $q->where('type', 'decisions')->latest()])
            ->get();

        $decisionKeywords = collect();
        foreach ($meetingsWithDecisions as $meeting) {
            $extraction = $meeting->extractions->first();
            if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
                continue;
            }

            foreach ($extraction->structured_data as $decision) {
                $decisionText = $decision['decision'] ?? '';
                $keywords = $this->extractKeywords(mb_strtolower($decisionText));
                $decisionKeywords->push([
                    'meeting_id' => $meeting->id,
                    'keywords' => $keywords,
                ]);
            }
        }

        $result = [];

        foreach ($topicFrequency as $topicData) {
            if ($topicData['count'] < 3) {
                continue;
            }

            $topicKeywords = $this->extractKeywords(mb_strtolower($topicData['topic']));
            $topicMeetingIds = collect($topicData['meetings'])->pluck('id')->toArray();

            $hasDecision = $decisionKeywords
                ->filter(fn ($d) => in_array($d['meeting_id'], $topicMeetingIds))
                ->contains(function ($d) use ($topicKeywords) {
                    foreach ($topicKeywords as $keyword) {
                        foreach ($d['keywords'] as $decisionKeyword) {
                            if (str_contains($decisionKeyword, $keyword) || str_contains($keyword, $decisionKeyword)) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            if (! $hasDecision) {
                $result[] = [
                    'topic' => $topicData['topic'],
                    'occurrences' => $topicData['count'],
                    'meetings' => $topicData['meetings'],
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{total: int, by_severity: array{high: int, medium: int, low: int}, recent_risks: array}
     */
    public function getRiskSummary(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $meetings = $this->baseMeetingQuery($orgId, $start, $end)
            ->whereHas('extractions', fn (Builder $q) => $q->where('type', 'risks'))
            ->with(['extractions' => fn ($q) => $q->where('type', 'risks')->latest()])
            ->get();

        $allRisks = collect();

        foreach ($meetings as $meeting) {
            $extraction = $meeting->extractions->first();
            if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
                continue;
            }

            foreach ($extraction->structured_data as $risk) {
                $allRisks->push([
                    'risk' => $risk['risk'] ?? $risk['description'] ?? '',
                    'severity' => mb_strtolower($risk['severity'] ?? $risk['level'] ?? 'medium'),
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                    'meeting_date' => $meeting->meeting_date?->toDateString(),
                    'created_at' => $extraction->created_at->toDateTimeString(),
                ]);
            }
        }

        $bySeverity = [
            'high' => $allRisks->where('severity', 'high')->count(),
            'medium' => $allRisks->where('severity', 'medium')->count(),
            'low' => $allRisks->where('severity', 'low')->count(),
        ];

        $recentRisks = $allRisks->sortByDesc('created_at')->take(10)->values()->toArray();

        return [
            'total' => $allRisks->count(),
            'by_severity' => $bySeverity,
            'recent_risks' => $recentRisks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMetrics(int $orgId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        return [
            'topic_frequency' => $this->getTopicFrequency($orgId, $start, $end),
            'decision_follow_through' => $this->getDecisionFollowThrough($orgId, $start, $end),
            'assignee_performance' => $this->getAssigneePerformance($orgId, $start, $end),
            'unresolved_patterns' => $this->getUnresolvedPatterns($orgId, $start, $end),
            'risk_summary' => $this->getRiskSummary($orgId, $start, $end),
        ];
    }

    /**
     * @return Builder<MinutesOfMeeting>
     */
    private function baseMeetingQuery(int $orgId, ?Carbon $start = null, ?Carbon $end = null): Builder
    {
        $query = MinutesOfMeeting::query()
            ->where('organization_id', $orgId);

        if ($start && $end) {
            $query->whereBetween('meeting_date', [$start, $end]);
        }

        return $query;
    }

    /**
     * Extract meaningful keywords from text.
     *
     * @return array<string>
     */
    private function extractKeywords(string $text): array
    {
        $stopWords = ['the', 'a', 'an', 'is', 'was', 'are', 'were', 'be', 'been', 'to', 'of', 'and', 'in', 'that', 'it', 'for', 'on', 'with', 'as', 'at', 'by', 'from', 'or', 'will', 'we', 'have', 'has', 'had', 'this', 'not', 'but', 'they', 'which', 'do', 'does', 'did', 'should', 'would', 'could', 'can', 'may', 'use', 'using', 'used'];

        $words = preg_split('/\W+/', mb_strtolower($text));

        return array_values(array_filter($words, function (string $word) use ($stopWords) {
            return mb_strlen($word) >= 3 && ! in_array($word, $stopWords, true);
        }));
    }
}
