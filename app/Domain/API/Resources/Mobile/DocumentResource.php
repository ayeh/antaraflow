<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->file_size,
            'status' => $this->status,
            'download_url' => route('mobile.documents.download', ['document' => $this->id]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
