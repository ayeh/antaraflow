<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_reseller' => 'boolean',
            'allowed_plans' => 'array',
            'commission_rate' => 'decimal:2',
            'branding_overrides' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): \Database\Factories\ResellerSettingFactory
    {
        return \Database\Factories\ResellerSettingFactory::new();
    }
}
