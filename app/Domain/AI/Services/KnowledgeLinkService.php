<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\KnowledgeLink;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\KnowledgeLinkType;
use Illuminate\Support\Collection;

class KnowledgeLinkService
{
    /**
     * For each decision in extraction, find ActionItems with matching keywords.
     * Create 'follows_up' KnowledgeLink between MomExtraction and ActionItem.
     */
    public function autoLinkDecisionsToActionItems(MinutesOfMeeting $mom): void
    {
        $extraction = $mom->extractions()
            ->where('type', 'decisions')
            ->latest()
            ->first();

        if (! $extraction || empty($extraction->structured_data) || isset($extraction->structured_data['custom_template'])) {
            return;
        }

        $actionItems = $mom->actionItems()->get();

        foreach ($extraction->structured_data as $decision) {
            $decisionText = $decision['decision'] ?? '';
            if (empty($decisionText)) {
                continue;
            }

            $keywords = $this->extractKeywords($decisionText);
            $matchedItems = $actionItems->filter(function (ActionItem $item) use ($keywords): bool {
                $itemText = mb_strtolower($item->title.' '.($item->description ?? ''));

                foreach ($keywords as $keyword) {
                    if (str_contains($itemText, $keyword)) {
                        return true;
                    }
                }

                return false;
            });

            foreach ($matchedItems as $actionItem) {
                KnowledgeLink::query()->firstOrCreate(
                    [
                        'organization_id' => $mom->organization_id,
                        'source_type' => MomExtraction::class,
                        'source_id' => $extraction->id,
                        'target_type' => ActionItem::class,
                        'target_id' => $actionItem->id,
                        'link_type' => KnowledgeLinkType::FollowsUp,
                    ],
                    [
                        'strength' => 1.0,
                        'metadata' => ['decision_text' => $decisionText],
                    ]
                );
            }
        }
    }

    /**
     * For each MomTopic, find other meetings' topics with similar titles (LIKE).
     * Create 'related_to' links between MomTopic records.
     */
    public function autoLinkTopicsAcrossMeetings(MinutesOfMeeting $mom): void
    {
        $topics = $mom->topics()->get();

        foreach ($topics as $topic) {
            if (empty($topic->title)) {
                continue;
            }

            $keywords = $this->extractKeywords($topic->title);
            if (empty($keywords)) {
                continue;
            }

            $similarTopics = MomTopic::query()
                ->where('minutes_of_meeting_id', '!=', $mom->id)
                ->whereHas('minutesOfMeeting', fn ($q) => $q->where('organization_id', $mom->organization_id))
                ->where(function ($query) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $query->orWhere('title', 'LIKE', '%'.$keyword.'%');
                    }
                })
                ->limit(20)
                ->get();

            foreach ($similarTopics as $similarTopic) {
                KnowledgeLink::query()->firstOrCreate(
                    [
                        'organization_id' => $mom->organization_id,
                        'source_type' => MomTopic::class,
                        'source_id' => $topic->id,
                        'target_type' => MomTopic::class,
                        'target_id' => $similarTopic->id,
                        'link_type' => KnowledgeLinkType::RelatedTo,
                    ],
                    [
                        'strength' => 1.0,
                        'metadata' => [
                            'source_title' => $topic->title,
                            'target_title' => $similarTopic->title,
                        ],
                    ]
                );
            }
        }
    }

    /**
     * Find meetings linked through any KnowledgeLink.
     */
    public function getRelatedMeetings(MinutesOfMeeting $mom): Collection
    {
        $relatedMeetingIds = collect();

        // Links where this meeting's entities are the source
        $sourceLinks = KnowledgeLink::query()
            ->where('organization_id', $mom->organization_id)
            ->where(function ($query) use ($mom) {
                $query->where(function ($q) use ($mom) {
                    $q->where('source_type', MinutesOfMeeting::class)
                        ->where('source_id', $mom->id);
                })->orWhere(function ($q) use ($mom) {
                    $q->where('source_type', MomExtraction::class)
                        ->whereIn('source_id', $mom->extractions()->pluck('id'));
                })->orWhere(function ($q) use ($mom) {
                    $q->where('source_type', MomTopic::class)
                        ->whereIn('source_id', $mom->topics()->pluck('id'));
                });
            })
            ->get();

        // Links where this meeting's entities are the target
        $targetLinks = KnowledgeLink::query()
            ->where('organization_id', $mom->organization_id)
            ->where(function ($query) use ($mom) {
                $query->where(function ($q) use ($mom) {
                    $q->where('target_type', MinutesOfMeeting::class)
                        ->where('target_id', $mom->id);
                })->orWhere(function ($q) use ($mom) {
                    $q->where('target_type', MomExtraction::class)
                        ->whereIn('target_id', $mom->extractions()->pluck('id'));
                })->orWhere(function ($q) use ($mom) {
                    $q->where('target_type', MomTopic::class)
                        ->whereIn('target_id', $mom->topics()->pluck('id'));
                });
            })
            ->get();

        $allLinks = $sourceLinks->merge($targetLinks);

        foreach ($allLinks as $link) {
            $meetingId = $this->resolveMeetingIdFromLink($link, $mom);
            if ($meetingId && $meetingId !== $mom->id) {
                $relatedMeetingIds->push([
                    'meeting_id' => $meetingId,
                    'link_type' => $link->link_type,
                ]);
            }
        }

        $uniqueMeetingIds = $relatedMeetingIds->pluck('meeting_id')->unique()->values();

        $meetings = MinutesOfMeeting::query()
            ->whereIn('id', $uniqueMeetingIds)
            ->with('project')
            ->get();

        return $meetings->map(function (MinutesOfMeeting $meeting) use ($relatedMeetingIds) {
            $linkTypes = $relatedMeetingIds
                ->where('meeting_id', $meeting->id)
                ->pluck('link_type')
                ->unique()
                ->values();

            return (object) [
                'meeting' => $meeting,
                'link_types' => $linkTypes,
            ];
        });
    }

    /**
     * Chain of linked decisions across an organization.
     *
     * @return array<int, array{decision: string, meeting_title: string, meeting_id: int, link_type: string, created_at: string}>
     */
    public function getDecisionTrail(int $orgId, ?string $topicFilter = null): array
    {
        $query = KnowledgeLink::query()
            ->where('organization_id', $orgId)
            ->where('source_type', MomExtraction::class)
            ->with(['source', 'target'])
            ->orderBy('created_at', 'desc');

        $links = $query->get();

        $trail = [];

        foreach ($links as $link) {
            $extraction = $link->source;
            if (! $extraction instanceof MomExtraction) {
                continue;
            }

            $meeting = MinutesOfMeeting::find($extraction->minutes_of_meeting_id);
            if (! $meeting) {
                continue;
            }

            if ($topicFilter && ! str_contains(mb_strtolower($meeting->title), mb_strtolower($topicFilter))) {
                continue;
            }

            $decisions = $extraction->structured_data;
            if (empty($decisions) || isset($decisions['custom_template'])) {
                continue;
            }

            foreach ($decisions as $decision) {
                $trail[] = [
                    'decision' => $decision['decision'] ?? '',
                    'meeting_title' => $meeting->title,
                    'meeting_id' => $meeting->id,
                    'link_type' => $link->link_type->value,
                    'created_at' => $link->created_at->toIso8601String(),
                ];
            }
        }

        return $trail;
    }

    /**
     * Resolve the meeting ID from a knowledge link entity.
     */
    private function resolveMeetingIdFromLink(KnowledgeLink $link, MinutesOfMeeting $currentMom): ?int
    {
        // Check source side
        $sourceMeetingId = $this->getMeetingIdFromEntity($link->source_type, $link->source_id);
        // Check target side
        $targetMeetingId = $this->getMeetingIdFromEntity($link->target_type, $link->target_id);

        // Return the one that is not the current meeting
        if ($sourceMeetingId && $sourceMeetingId !== $currentMom->id) {
            return $sourceMeetingId;
        }

        if ($targetMeetingId && $targetMeetingId !== $currentMom->id) {
            return $targetMeetingId;
        }

        return null;
    }

    /**
     * Get the meeting ID from an entity type and ID.
     */
    private function getMeetingIdFromEntity(string $type, int $id): ?int
    {
        return match ($type) {
            MinutesOfMeeting::class => $id,
            MomExtraction::class => MomExtraction::find($id)?->minutes_of_meeting_id,
            MomTopic::class => MomTopic::find($id)?->minutes_of_meeting_id,
            ActionItem::class => ActionItem::find($id)?->minutes_of_meeting_id,
            default => null,
        };
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

        return array_values(array_filter($words, function (string $word) use ($stopWords): bool {
            return mb_strlen($word) >= 3 && ! in_array($word, $stopWords, true);
        }));
    }
}
