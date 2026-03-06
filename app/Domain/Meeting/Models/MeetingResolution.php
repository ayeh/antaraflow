<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Domain\Attendee\Models\MomAttendee;
use App\Support\Enums\ResolutionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingResolution extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ResolutionStatus::class,
        ];
    }

    protected static function newFactory(): \Database\Factories\MeetingResolutionFactory
    {
        return \Database\Factories\MeetingResolutionFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'meeting_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'mover_id');
    }

    public function seconder(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'seconder_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ResolutionVote::class, 'resolution_id');
    }
}
