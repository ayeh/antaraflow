<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomGuestAccess extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'token',
        'label',
        'email',
        'is_active',
        'expires_at',
        'last_accessed_at',
        'access_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomGuestAccessFactory
    {
        return \Database\Factories\MomGuestAccessFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    /**
     * A link a guest can actually open right now: enabled, and either without an
     * expiry or not yet past it. This is the single definition of "active" — the
     * public guest route and the "Shared" badge both use it, so the badge can never
     * claim a meeting is shared through a link that would return 404.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
