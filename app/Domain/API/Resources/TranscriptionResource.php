<?php

declare(strict_types=1);

namespace App\Domain\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'language' => $this->language,
            'status' => $this->status->value,
            'duration_seconds' => $this->duration_seconds,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->resource->relationLoaded('segments')) {
            $data['segments'] = $this->segments->map(fn ($segment) => [
                'start_time' => $segment->start_time,
                'end_time' => $segment->end_time,
                'text' => $segment->text,
                'speaker' => $segment->speaker,
            ])->values()->all();
        }

        return $data;
    }
}
