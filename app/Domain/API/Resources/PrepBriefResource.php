<?php

declare(strict_types=1);

namespace App\Domain\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepBriefResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'minutes_of_meeting_id' => $this->minutes_of_meeting_id,
            'content' => $this->content,
            'summary_highlights' => $this->summary_highlights,
            'estimated_prep_minutes' => $this->estimated_prep_minutes,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
