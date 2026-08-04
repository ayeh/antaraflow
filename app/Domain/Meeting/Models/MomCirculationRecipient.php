<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Domain\Attendee\Models\MomAttendee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomCirculationRecipient extends Model
{
    protected $fillable = [
        'mom_circulation_id',
        'mom_attendee_id',
        'name',
        'email',
        'token',
        'response',
        'responded_at',
        'deemed_confirmed_at',
        'first_opened_at',
        'last_opened_at',
        'open_count',
        'responded_ip',
        'responded_user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'deemed_confirmed_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function circulation(): BelongsTo
    {
        return $this->belongsTo(MomCirculation::class, 'mom_circulation_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'mom_attendee_id');
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }

    public function isConfirmed(): bool
    {
        return $this->response === 'confirmed';
    }

    public function isDeemedConfirmed(): bool
    {
        return $this->deemed_confirmed_at !== null;
    }

    public function hasOpened(): bool
    {
        return $this->open_count > 0;
    }
}
