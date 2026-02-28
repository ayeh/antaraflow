<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Enums\InputType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MomInput extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InputType::class,
            'is_primary' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomInputFactory
    {
        return \Database\Factories\MomInputFactory::new();
    }

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
