<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSeries extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'recurrence_pattern',
        'recurrence_config',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recurrence_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\MeetingSeriesFactory
    {
        return \Database\Factories\MeetingSeriesFactory::new();
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(MinutesOfMeeting::class);
    }
}
