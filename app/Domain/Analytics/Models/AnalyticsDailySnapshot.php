<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsDailySnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_meetings',
        'total_action_items',
        'completed_action_items',
        'overdue_action_items',
        'total_attendees',
        'ai_usage_count',
        'avg_meeting_duration_minutes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
