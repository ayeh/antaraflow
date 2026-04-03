<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'user_id',
        'name',
        'email',
        'phone',
        'company',
        'role',
        'rsvp_status',
        'is_present',
        'is_external',
        'department',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AttendeeRole::class,
            'rsvp_status' => RsvpStatus::class,
            'is_present' => 'boolean',
            'is_external' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomAttendeeFactory
    {
        return \Database\Factories\MomAttendeeFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
