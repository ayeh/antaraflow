<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeetingSearchService
{
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MinutesOfMeeting::query()->with(['createdBy', 'series', 'tags', 'project', 'actionItems', 'attendees']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('mom_number', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('meeting_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('meeting_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['series_id'])) {
            $query->where('meeting_series_id', $filters['series_id']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (! empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('mom_tags.id', (array) $filters['tags']);
            });
        }

        return $query->latest('meeting_date')->paginate($perPage);
    }
}
