<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Models\User;
use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'push_token',
        'platform',
        'app_version',
        'locale',
        'last_seen_at',
        'revoked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<UserDevice>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->whereNotNull('push_token');
    }
}
