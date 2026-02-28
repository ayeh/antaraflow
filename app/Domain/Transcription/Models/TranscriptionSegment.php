<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionSegment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
            'start_time' => 'float',
            'end_time' => 'float',
            'confidence' => 'float',
        ];
    }

    protected static function newFactory(): \Database\Factories\TranscriptionSegmentFactory
    {
        return \Database\Factories\TranscriptionSegmentFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function (Builder $query) {
            $query->orderBy('sequence_order');
        });
    }

    public function audioTranscription(): BelongsTo
    {
        return $this->belongsTo(AudioTranscription::class);
    }
}
