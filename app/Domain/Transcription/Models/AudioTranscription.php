<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioTranscription extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['recorded_by_label'];

    protected $fillable = [
        'minutes_of_meeting_id',
        'uploaded_by',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'duration_seconds',
        'language',
        'device_label',
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

    /**
     * The Inputs list line: "Noor Ariff · Chrome on macOS · Web".
     *
     * Null when this recording predates device capture, which is the signal
     * the UI uses to fall back to its plain Browser/Live source badge. The
     * uploader's name is only joined on when the relation is already loaded,
     * so appending this attribute never triggers an N+1.
     */
    protected function recordedByLabel(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->device_label === null) {
                    return null;
                }

                $name = $this->relationLoaded('uploadedBy')
                    ? $this->uploadedBy?->name
                    : null;

                return $name !== null
                    ? $name.' · '.$this->device_label
                    : $this->device_label;
            },
        );
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptionSegment::class);
    }
}
