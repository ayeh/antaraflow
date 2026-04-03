<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Str;

class GlobalSearchService
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function search(string $query, int $organizationId, int $limit = 20): array
    {
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);

        return [
            'meetings' => $this->searchMeetings($escaped, $organizationId, min($limit, 10)),
            'action_items' => $this->searchActionItems($escaped, $organizationId, min($limit, 5)),
            'projects' => $this->searchProjects($escaped, $organizationId, min($limit, 3)),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function searchMeetings(string $query, int $organizationId, int $limit): array
    {
        return MinutesOfMeeting::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('mom_number', 'like', "%{$query}%")
                    ->orWhere('summary', 'like', "%{$query}%");
            })
            ->latest('meeting_date')
            ->limit($limit)
            ->get(['id', 'title', 'mom_number', 'status', 'meeting_date'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'mom_number' => $m->mom_number,
                'status' => $m->status->value,
                'meeting_date' => $m->meeting_date?->toDateString(),
                'url' => route('meetings.show', $m),
                'type' => 'meeting',
            ])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchActionItems(string $query, int $organizationId, int $limit): array
    {
        return ActionItem::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->with('meeting:id,title')
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'status', 'priority', 'minutes_of_meeting_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'status' => $a->status->value,
                'priority' => $a->priority->value,
                'meeting_title' => $a->meeting?->title,
                'url' => $a->meeting ? route('meetings.action-items.show', [$a->meeting, $a]) : null,
                'type' => 'action_item',
            ])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchProjects(string $query, int $organizationId, int $limit): array
    {
        return Project::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'description'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->name,
                'description' => Str::limit($p->description, 100),
                'url' => route('projects.show', $p),
                'type' => 'project',
            ])
            ->toArray();
    }
}
