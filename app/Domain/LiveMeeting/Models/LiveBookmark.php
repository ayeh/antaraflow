<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Models;

use App\Models\User;
use App\Support\Enums\BookmarkKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveBookmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_meeting_session_id',
        'created_by',
        'at_seconds',
        'label',
        'kind',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'at_seconds' => 'float',
            'kind' => BookmarkKind::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveMeetingSession::class, 'live_meeting_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
