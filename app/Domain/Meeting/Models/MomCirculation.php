<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MomCirculation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'minutes_of_meeting_id',
        'subject',
        'body_note',
        'deadline_at',
        'status',
        'round',
        'sent_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'closed_at' => 'datetime',
            'round' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MomCirculationRecipient::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
