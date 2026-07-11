<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAiBudget extends Model
{
    protected $fillable = [
        'organization_id',
        'daily_limit',
        'monthly_limit',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_limit' => 'float',
            'monthly_limit' => 'float',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
