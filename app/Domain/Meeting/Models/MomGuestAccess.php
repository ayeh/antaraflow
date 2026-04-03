<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomGuestAccess extends Model
{
    use BelongsToOrganization;

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

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }
}
