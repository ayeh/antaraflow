<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Support\Collection;

class DecisionTrackerService
{
    /**
     * Get the follow-up status for each decision in a meeting.
     *
     * @return array<int, array{decision: string, context: ?string, made_by: ?string, status: string, linked_action_items: array}>
     */
    public function getDecisionStatus(MinutesOfMeeting $mom): array
    {
        $extraction = $mom->extractions()
            ->where('type', 'decisions')
            ->latest()
            ->first();

        if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
            return [];
        }

        $actionItems = $mom->actionItems()->with('assignedTo')->get();

        return array_map(function (array $decision) use ($actionItems, $extraction) {
            $decisionText = $decision['decision'] ?? '';
            $linkedItems = $this->findRelatedActionItems($decisionText, $actionItems);

            $daysSinceDecision = $extraction->created_at->diffInDays(now());
            $status = match (true) {
                $linkedItems->isNotEmpty() => 'followed_up',
                $daysSinceDecision <= 7 => 'pending',
                default => 'stale',
            };

            return [
                'decision' => $decisionText,
                'context' => $decision['context'] ?? null,
                'made_by' => $decision['made_by'] ?? null,
                'status' => $status,
                'days_since' => $daysSinceDecision,
                'linked_action_items' => $linkedItems->map(fn (ActionItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'status' => $item->status->value,
                    'assignee' => $item->assignedTo?->name,
                ])->values()->toArray(),
            ];
        }, $extraction->structured_data);
    }

    /**
     * Get all stale decisions across an organization.
     */
    public function getStaleDecisions(int $organizationId, int $staleDays = 7): Collection
    {
        $staleDecisions = collect();

        $meetings = MinutesOfMeeting::query()
            ->where('organization_id', $organizationId)
            ->whereHas('extractions', fn ($q) => $q->where('type', 'decisions'))
            ->with(['extractions' => fn ($q) => $q->where('type', 'decisions')->latest(), 'actionItems', 'createdBy'])
            ->get();

        foreach ($meetings as $meeting) {
            $extraction = $meeting->extractions->first();
            if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
                continue;
            }

            if ($extraction->created_at->diffInDays(now()) < $staleDays) {
                continue;
            }

            foreach ($extraction->structured_data as $decision) {
                $decisionText = $decision['decision'] ?? '';
                $linkedItems = $this->findRelatedActionItems($decisionText, $meeting->actionItems);

                if ($linkedItems->isEmpty()) {
                    $staleDecisions->push([
                        'meeting' => $meeting,
                        'decision' => $decisionText,
                        'context' => $decision['context'] ?? null,
                        'made_by' => $decision['made_by'] ?? null,
                        'days_since' => $extraction->created_at->diffInDays(now()),
                    ]);
                }
            }
        }

        return $staleDecisions;
    }

    /**
     * Find action items related to a decision by keyword matching.
     */
    private function findRelatedActionItems(string $decisionText, Collection $actionItems): Collection
    {
        if (empty($decisionText)) {
            return collect();
        }

        $keywords = $this->extractKeywords($decisionText);

        return $actionItems->filter(function (ActionItem $item) use ($keywords) {
            $itemText = mb_strtolower($item->title.' '.($item->description ?? ''));

            foreach ($keywords as $keyword) {
                if (str_contains($itemText, $keyword)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Extract meaningful keywords from decision text.
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
