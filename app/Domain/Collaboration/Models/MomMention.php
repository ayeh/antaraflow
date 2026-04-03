<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomMention extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'comment_id',
        'mentioned_user_id',
        'minutes_of_meeting_id',
        'is_read',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
