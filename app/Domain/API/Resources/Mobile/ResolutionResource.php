<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use App\Support\Enums\ResolutionStatus;
use App\Support\Enums\VoteChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ResolutionResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?int $viewerAttendeeId = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $votingOpen = $this->status === ResolutionStatus::Proposed;

        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'resolution_number' => $this->resolution_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'mover' => $this->whenLoaded('mover', fn () => $this->mover?->only(['id', 'name'])),
            'seconder' => $this->whenLoaded('seconder', fn () => $this->seconder?->only(['id', 'name'])),
            'tally' => $this->tally(),
            'my_vote' => $this->myVote(),
            'voting_open' => $votingOpen,
            'permissions' => [
                'can_vote' => $votingOpen && Gate::allows('vote', $this->resource),
                'can_update' => Gate::allows('update', $this->resource),
                'can_delete' => Gate::allows('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, int>|null */
    private function tally(): ?array
    {
        if (! $this->relationLoaded('votes')) {
            return null;
        }

        $counts = [];

        foreach (VoteChoice::cases() as $choice) {
            $counts[$choice->value] = $this->votes->where('vote', $choice)->count();
        }

        $eligible = $this->relationLoaded('meeting') && $this->meeting?->relationLoaded('attendees')
            ? $this->meeting->attendees->count()
            : null;

        if ($eligible !== null) {
            $counts['not_voted'] = max(0, $eligible - $this->votes->count());
            $counts['total_eligible'] = $eligible;
        }

        return $counts;
    }

    private function myVote(): ?string
    {
        if ($this->viewerAttendeeId === null || ! $this->relationLoaded('votes')) {
            return null;
        }

        return $this->votes->firstWhere('attendee_id', $this->viewerAttendeeId)?->vote?->value;
    }
}
