<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'locale' => $this->language ?? config('app.locale'),
            'timezone' => $this->timezone,
            'onboarding_completed_at' => $this->onboarding_completed_at?->toIso8601String(),
        ];
    }
}
