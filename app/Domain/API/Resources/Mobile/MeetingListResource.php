<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use App\Support\Enums\ActionItemStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shape used in lists. Deliberately excludes `content`, which is the whole
 * minutes document and would dominate the payload on a phone.
 */
class MeetingListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mom_number' => $this->mom_number,
            'title' => $this->title,
            'meeting_type' => $this->meeting_type?->value,
            'status' => $this->status->value,
            'meeting_date' => $this->meeting_date?->toIso8601String(),
            'location' => $this->location,
            'duration_minutes' => $this->duration_minutes,
            'attendee_count' => $this->whenCounted('attendees'),
            'action_item_counts' => $this->actionItemCounts(),
            'has_transcription' => (bool) ($this->transcriptions_count ?? 0),
            'has_live_session' => (bool) ($this->active_live_sessions_count ?? 0),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, int>|null */
    private function actionItemCounts(): ?array
    {
        if (! $this->relationLoaded('actionItems')) {
            return null;
        }

        $counts = [];

        foreach (ActionItemStatus::cases() as $status) {
            $counts[$status->value] = $this->actionItems
                ->where('status', $status)
                ->count();
        }

        return $counts;
    }
}
