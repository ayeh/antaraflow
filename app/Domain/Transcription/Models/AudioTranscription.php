<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioTranscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'uploaded_by',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'duration_seconds',
        'language',
        'status',
        'full_text',
        'confidence_score',
        'provider',
        'provider_metadata',
        'retry_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TranscriptionStatus::class,
            'provider_metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\AudioTranscriptionFactory
    {
        return \Database\Factories\AudioTranscriptionFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptionSegment::class);
    }
}
