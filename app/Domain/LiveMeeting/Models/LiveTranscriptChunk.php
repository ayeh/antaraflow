<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Models;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveTranscriptChunk extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ChunkStatus::class,
            'start_time' => 'double',
            'end_time' => 'double',
            'confidence' => 'double',
            'chunk_number' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\LiveTranscriptChunkFactory
    {
        return \Database\Factories\LiveTranscriptChunkFactory::new();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveMeetingSession::class, 'live_meeting_session_id');
    }
}
