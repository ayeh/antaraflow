<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageTracking extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\UsageTrackingFactory
    {
        return \Database\Factories\UsageTrackingFactory::new();
    }
}
