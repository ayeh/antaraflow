<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoiceNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'duration_seconds' => (int) $this->duration_seconds,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->file_size,
            'transcript' => $this->transcript,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
