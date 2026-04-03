<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomAiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'user_id',
        'role',
        'message',
        'context',
        'token_usage',
        'provider',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomAiConversationFactory
    {
        return \Database\Factories\MomAiConversationFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
