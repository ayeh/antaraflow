<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrRegistrationToken extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'required_fields' => 'array',
            'max_attendees' => 'integer',
            'registrations_count' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isFull(): bool
    {
        return $this->max_attendees !== null && $this->registrations_count >= $this->max_attendees;
    }

    public function incrementRegistrations(): void
    {
        $this->increment('registrations_count');
    }
}
