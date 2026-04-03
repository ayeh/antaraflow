<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderConfig extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'provider',
        'display_name',
        'api_key_encrypted',
        'model',
        'base_url',
        'settings',
        'is_default',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_key_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'settings' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\AiProviderConfigFactory
    {
        return \Database\Factories\AiProviderConfigFactory::new();
    }
}
