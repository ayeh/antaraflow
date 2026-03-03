<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\SubscriptionPlanFactory
    {
        return \Database\Factories\SubscriptionPlanFactory::new();
    }

    /** @return HasMany<OrganizationSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }
}
