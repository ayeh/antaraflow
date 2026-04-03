<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'type',
        'content',
        'structured_data',
        'provider',
        'model',
        'confidence_score',
        'token_usage',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
            'confidence_score' => 'float',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomExtractionFactory
    {
        return \Database\Factories\MomExtractionFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }
}
