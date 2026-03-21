<?php

declare(strict_types=1);

namespace App\Domain\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'rsvp_status' => $this->rsvp_status->value,
            'is_present' => $this->is_present,
            'is_external' => $this->is_external,
            'department' => $this->department,
            'company' => $this->company,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
