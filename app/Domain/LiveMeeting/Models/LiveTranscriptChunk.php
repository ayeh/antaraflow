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

    protected $fillable = [
        'live_meeting_session_id',
        'chunk_number',
        'audio_file_path',
        'text',
        'segments',
        'speaker',
        'start_time',
        'end_time',
        'confidence',
        'peak_dbfs',
        'speech_dbfs',
        'noise_dbfs',
        'status',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ChunkStatus::class,
            'segments' => 'array',
            'start_time' => 'double',
            'end_time' => 'double',
            'confidence' => 'double',
            'peak_dbfs' => 'double',
            'speech_dbfs' => 'double',
            'noise_dbfs' => 'double',
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
