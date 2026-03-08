<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPrepBrief extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'summary_highlights' => 'array',
            'sections_read' => 'array',
            'generated_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\MeetingPrepBriefFactory
    {
        return \Database\Factories\MeetingPrepBriefFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'attendee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsViewed(): void
    {
        $this->update(['viewed_at' => now()]);
    }

    public function markSectionRead(string $section): void
    {
        $sections = $this->sections_read ?? [];
        if (! in_array($section, $sections, true)) {
            $sections[] = $section;
            $this->update(['sections_read' => $sections]);
        }
    }
}
