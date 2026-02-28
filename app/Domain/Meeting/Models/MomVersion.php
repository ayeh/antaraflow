<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomVersion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomVersionFactory
    {
        return \Database\Factories\MomVersionFactory::new();
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
