<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'teams_webhook_url' => 'encrypted',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\OrganizationFactory
    {
        return \Database\Factories\OrganizationFactory::new();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_organization_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_organization_id');
    }

    public function subOrganizations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_organization_id');
    }

    public function resellerSetting(): HasOne
    {
        return $this->hasOne(ResellerSetting::class);
    }

    protected function getJsonCastFlags($key): int
    {
        $flags = parent::getJsonCastFlags($key);

        if ($key === 'settings') {
            $flags |= JSON_PRESERVE_ZERO_FRACTION;
        }

        return $flags;
    }

    public function isReseller(): bool
    {
        return (bool) $this->resellerSetting?->is_reseller;
    }

    public function hasTeamsWebhook(): bool
    {
        return $this->teams_webhook_url !== null && $this->teams_webhook_url !== '';
    }
}
