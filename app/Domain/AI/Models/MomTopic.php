<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'title',
        'description',
        'duration_minutes',
        'sort_order',
        'related_segments',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'related_segments' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomTopicFactory
    {
        return \Database\Factories\MomTopicFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }
}
