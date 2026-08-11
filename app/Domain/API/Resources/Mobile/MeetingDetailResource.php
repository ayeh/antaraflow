<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the meeting screen needs in one response.
 *
 * `permissions` is included on purpose: the app must never infer what a person
 * may do from their role, because the policies also weigh meeting status.
 */
class MeetingDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mom_number' => $this->mom_number,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'status' => $this->status->value,
            'meeting_type' => $this->meeting_type?->value,
            'meeting_date' => $this->meeting_date?->toIso8601String(),
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'meeting_platform' => $this->meeting_platform?->value,
            'duration_minutes' => $this->duration_minutes,
            'language' => $this->language,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'series' => $this->whenLoaded('series', fn () => $this->series ? [
                'id' => $this->series->id,
                'name' => $this->series->name,
            ] : null),
            'attendees' => AttendeeResource::collection($this->whenLoaded('attendees')),
            'action_items' => ActionItemResource::collection($this->whenLoaded('actionItems')),
            'extractions' => ExtractionResource::collection($this->whenLoaded('extractions')),
            'resolutions' => ResolutionResource::collection($this->whenLoaded('resolutions')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'voice_notes' => VoiceNoteResource::collection($this->whenLoaded('voiceNotes')),
            'transcriptions' => TranscriptionResource::collection($this->whenLoaded('transcriptions')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'live_session' => $this->whenLoaded('liveSessions', fn () => $this->liveSessions
                ->firstWhere('status', \App\Domain\LiveMeeting\Enums\LiveSessionStatus::Active)
                ?->only(['id', 'status', 'started_at'])),
            'permissions' => [
                'can_update' => Gate::allows('update', $this->resource),
                'can_delete' => Gate::allows('delete', $this->resource),
                'can_finalize' => Gate::allows('finalize', $this->resource),
                'can_approve' => Gate::allows('approve', $this->resource),
                'can_start_live' => Gate::allows('startLive', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
