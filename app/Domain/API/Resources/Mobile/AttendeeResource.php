<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'position' => $this->position,
            'department' => $this->department,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'rsvp_status' => $this->rsvp_status instanceof \BackedEnum ? $this->rsvp_status->value : $this->rsvp_status,
            'is_present' => (bool) $this->is_present,
            'is_external' => (bool) $this->is_external,
        ];
    }
}
