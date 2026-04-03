<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomManualNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'created_by',
        'title',
        'content',
        'sort_order',
    ];

    protected static function newFactory(): \Database\Factories\MomManualNoteFactory
    {
        return \Database\Factories\MomManualNoteFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
