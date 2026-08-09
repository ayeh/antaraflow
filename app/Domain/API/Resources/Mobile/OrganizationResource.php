<?php

declare(strict_types=1);

namespace App\Domain\API\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrganizationResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?int $currentOrganizationId = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'role' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->role),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'is_current' => $this->currentOrganizationId !== null
                ? $this->id === $this->currentOrganizationId
                : null,
        ];
    }
}
