<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomJoinSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allow_external_join' => 'boolean',
            'require_rsvp' => 'boolean',
            'auto_notify' => 'boolean',
            'notification_config' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomJoinSettingFactory
    {
        return \Database\Factories\MomJoinSettingFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }
}
