<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\ApiKeyFactory
    {
        return \Database\Factories\ApiKeyFactory::new();
    }
}
