<?php

declare(strict_types=1);

namespace App\Domain\Export\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomExport extends Model
{
    protected $fillable = [
        'minutes_of_meeting_id',
        'user_id',
        'format',
        'file_path',
        'file_size',
        'downloaded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
