<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'language' => $this->language,
            'duration_seconds' => (int) $this->duration_seconds,
            'confidence_score' => $this->confidence_score,
            'provider' => $this->provider,
            'full_text' => $this->when($request->boolean('include_text'), $this->full_text),
            'segments' => TranscriptionSegmentResource::collection($this->whenLoaded('segments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
