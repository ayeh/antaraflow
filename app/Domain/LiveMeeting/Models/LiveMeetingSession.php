<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Models;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveMeetingSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\LiveMeetingSessionFactory
    {
        return \Database\Factories\LiveMeetingSessionFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(LiveTranscriptChunk::class)->orderBy('chunk_number');
    }

    public function getCompletedTranscriptText(): string
    {
        return $this->chunks()
            ->where('status', ChunkStatus::Completed)
            ->whereNotNull('text')
            ->orderBy('chunk_number')
            ->pluck('text')
            ->join("\n");
    }

    public function isActive(): bool
    {
        return $this->status === LiveSessionStatus::Active;
    }
}
