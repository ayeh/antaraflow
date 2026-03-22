<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\AI\Models\ProactiveInsight;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Builder;

class MemoAdvisorService
{
    public function __construct(
        private readonly DecisionTrackerService $decisionTracker,
    ) {}

    public function generateInsights(int $orgId): void
    {
        $this->detectRecurringTopicsWithoutDecisions($orgId);
        $this->detectDecisionsWithoutFollowUp($orgId);
        $this->detectReraisedActionItems($orgId);
        $this->detectOverduePatterns($orgId);
    }

    /**
     * Topics appearing in 3+ meetings with no decision in those meetings.
     */
    public function detectRecurringTopicsWithoutDecisions(int $orgId): void
    {
        $topics = MomTopic::query()
            ->whereHas('minutesOfMeeting', fn (Builder $q) => $q->where('organization_id', $orgId))
            ->with(['minutesOfMeeting' => fn ($q) => $q->select('id', 'title', 'meeting_date')])
            ->get();

        $grouped = $topics->groupBy(fn (MomTopic $topic) => mb_strtolower(trim($topic->title)));

        foreach ($grouped as $normalizedTitle => $topicGroup) {
            $meetingIds = $topicGroup->pluck('minutes_of_meeting_id')->unique()->toArray();

            if (count($meetingIds) < 3) {
                continue;
            }

            $hasDecision = MomExtraction::query()
                ->where('type', 'decisions')
                ->whereIn('minutes_of_meeting_id', $meetingIds)
                ->exists();

            if ($hasDecision) {
                continue;
            }

            $signature = md5('recurring_topic:'.$normalizedTitle);

            if ($this->insightExists($orgId, 'recurring_topic', $signature)) {
                continue;
            }

            $topicTitle = $topicGroup->first()->title;
            $meetingCount = count($meetingIds);

            ProactiveInsight::create([
                'organization_id' => $orgId,
                'type' => 'recurring_topic',
                'title' => "Recurring topic: {$topicTitle}",
                'description' => "The topic \"{$topicTitle}\" has been discussed in {$meetingCount} meetings without any recorded decisions. Consider scheduling a dedicated session to resolve this topic.",
                'severity' => $meetingCount >= 5 ? 'warning' : 'info',
                'metadata' => [
                    'signature' => $signature,
                    'topic' => $topicTitle,
                    'meeting_ids' => $meetingIds,
                    'meeting_count' => $meetingCount,
                ],
                'generated_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        }
    }

    /**
     * Decisions without follow-up action items (stale decisions).
     */
    public function detectDecisionsWithoutFollowUp(int $orgId): void
    {
        $staleDecisions = $this->decisionTracker->getStaleDecisions($orgId, staleDays: 7);

        foreach ($staleDecisions as $stale) {
            $decisionText = $stale['decision'] ?? '';
            $signature = md5('decision_no_followup:'.mb_strtolower($decisionText).':'.$stale['meeting']->id);

            if ($this->insightExists($orgId, 'decision_no_followup', $signature)) {
                continue;
            }

            $daysSince = $stale['days_since'];

            ProactiveInsight::create([
                'organization_id' => $orgId,
                'type' => 'decision_no_followup',
                'title' => 'Decision without follow-up',
                'description' => "A decision made {$daysSince} days ago in \"{$stale['meeting']->title}\" has no associated action items: \"{$decisionText}\"",
                'severity' => $daysSince >= 14 ? 'warning' : 'info',
                'metadata' => [
                    'signature' => $signature,
                    'decision' => $decisionText,
                    'meeting_id' => $stale['meeting']->id,
                    'meeting_title' => $stale['meeting']->title,
                    'days_since' => $daysSince,
                ],
                'generated_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        }
    }

    /**
     * Action items carried forward 2+ times.
     */
    public function detectReraisedActionItems(int $orgId): void
    {
        $carriedItems = ActionItem::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('carried_from_id')
            ->with(['carriedFrom', 'meeting', 'assignedTo'])
            ->get();

        foreach ($carriedItems as $item) {
            $carryCount = $this->countCarryChain($item);

            if ($carryCount < 2) {
                continue;
            }

            $signature = md5('reraised_action_item:'.$item->id);

            if ($this->insightExists($orgId, 'reraised_action_item', $signature)) {
                continue;
            }

            ProactiveInsight::create([
                'organization_id' => $orgId,
                'type' => 'reraised_action_item',
                'title' => "Action item carried forward {$carryCount} times",
                'description' => "The action item \"{$item->title}\" has been carried forward {$carryCount} times. Consider reassigning or breaking it into smaller tasks.",
                'severity' => $carryCount >= 3 ? 'critical' : 'warning',
                'metadata' => [
                    'signature' => $signature,
                    'action_item_id' => $item->id,
                    'action_item_title' => $item->title,
                    'carry_count' => $carryCount,
                    'assignee' => $item->assignedTo?->name,
                ],
                'generated_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);
        }
    }

    /**
     * Assignees with 3+ overdue items.
     */
    public function detectOverduePatterns(int $orgId): void
    {
        $overdueItems = ActionItem::query()
            ->where('organization_id', $orgId)
            ->whereNotIn('status', [
                ActionItemStatus::Completed,
                ActionItemStatus::Cancelled,
                ActionItemStatus::CarriedForward,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotNull('assigned_to')
            ->with('assignedTo')
            ->get();

        $grouped = $overdueItems->groupBy('assigned_to');

        foreach ($grouped as $assigneeId => $items) {
            if ($items->count() < 3) {
                continue;
            }

            $signature = md5('overdue_pattern:'.$assigneeId.':'.now()->format('Y-W'));

            if ($this->insightExists($orgId, 'overdue_pattern', $signature)) {
                continue;
            }

            $assigneeName = $items->first()->assignedTo?->name ?? 'Unknown';
            $count = $items->count();

            ProactiveInsight::create([
                'organization_id' => $orgId,
                'type' => 'overdue_pattern',
                'title' => "{$assigneeName} has {$count} overdue items",
                'description' => "{$assigneeName} currently has {$count} overdue action items. This may indicate capacity issues or blocked tasks that need attention.",
                'severity' => $count >= 5 ? 'critical' : 'warning',
                'metadata' => [
                    'signature' => $signature,
                    'assignee_id' => $assigneeId,
                    'assignee_name' => $assigneeName,
                    'overdue_count' => $count,
                    'action_item_ids' => $items->pluck('id')->toArray(),
                ],
                'generated_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        }
    }

    /**
     * Count how many times an action item has been carried forward by traversing the chain.
     */
    private function countCarryChain(ActionItem $item): int
    {
        $count = 0;
        $current = $item;

        while ($current->carried_from_id !== null) {
            $count++;
            $current = ActionItem::withoutGlobalScopes()->find($current->carried_from_id);
            if (! $current) {
                break;
            }
        }

        return $count;
    }

    /**
     * Check if a similar insight already exists (active and not dismissed).
     */
    private function insightExists(int $orgId, string $type, string $signature): bool
    {
        return ProactiveInsight::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('type', $type)
            ->where('is_dismissed', false)
            ->where('metadata->signature', $signature)
            ->exists();
    }
}
