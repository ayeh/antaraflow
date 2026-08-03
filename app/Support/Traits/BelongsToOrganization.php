<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Domain\Account\Models\Organization;
use App\Infrastructure\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id && auth()->check()) {
                $model->organization_id = auth()->user()->current_organization_id;
            }
        });
    }

    /**
     * Create a record belonging to an explicit organisation.
     *
     * organization_id is deliberately kept out of $fillable so a request can never
     * mass-assign it. That also means passing it to create() silently drops it, and
     * the creating hook above then falls back to the *acting user's* organisation —
     * wrong whenever the record belongs elsewhere, and a NOT NULL failure when there
     * is no authenticated user at all (queued jobs, console commands, API tokens).
     *
     * Use this wherever the owning organisation is known explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createForOrganization(int $organizationId, array $attributes): static
    {
        $model = new static($attributes);
        $model->organization_id = $organizationId;
        $model->save();

        return $model;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
