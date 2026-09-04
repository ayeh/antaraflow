<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingConsent extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'acknowledged_by',
        'notice_version',
        'includes_tab_audio',
        'ip_address',
        'user_agent',
        'acknowledged_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'includes_tab_audio' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\RecordingConsentFactory
    {
        return \Database\Factories\RecordingConsentFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
