<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'key',
        'secret_hash',
        'permissions',
        'allowed_ips',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'allowed_ips' => 'array',
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
