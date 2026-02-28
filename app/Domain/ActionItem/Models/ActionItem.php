<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionItem extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => ActionItemPriority::class,
            'status' => ActionItemStatus::class,
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\ActionItemFactory
    {
        return \Database\Factories\ActionItemFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_id');
    }

    public function carriedTo(): HasMany
    {
        return $this->hasMany(self::class, 'carried_from_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ActionItemHistory::class);
    }
}
