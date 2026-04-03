<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Domain\Attendee\Models\MomAttendee;
use App\Support\Enums\VoteChoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResolutionVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'resolution_id',
        'attendee_id',
        'vote',
        'voted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vote' => VoteChoice::class,
            'voted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\ResolutionVoteFactory
    {
        return \Database\Factories\ResolutionVoteFactory::new();
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(MeetingResolution::class, 'resolution_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'attendee_id');
    }
}
