<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use App\Support\Enums\ActionItemStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ActionItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'carried_from_id' => $this->carried_from_id,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'avatar_url' => $this->assignedTo->avatar_path
                    ? Storage::url($this->assignedTo->avatar_path)
                    : null,
            ] : null),
            'meeting' => $this->whenLoaded('meeting', fn () => $this->meeting ? [
                'id' => $this->meeting->id,
                'title' => $this->meeting->title,
                'mom_number' => $this->meeting->mom_number,
            ] : null),
            'permissions' => [
                'can_update' => Gate::allows('update', $this->resource),
                'can_delete' => Gate::allows('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function isOverdue(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        if (in_array($this->status, [ActionItemStatus::Completed, ActionItemStatus::Cancelled], true)) {
            return false;
        }

        return $this->due_date->isPast();
    }
}
