<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptionSegmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'speaker' => $this->speaker,
            'text' => $this->text,
            'start_time' => (float) $this->start_time,
            'end_time' => (float) $this->end_time,
            'confidence' => $this->confidence !== null ? (float) $this->confidence : null,
        ];
    }
}
