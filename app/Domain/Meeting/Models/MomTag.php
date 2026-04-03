<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MomTag extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    protected static function newFactory(): \Database\Factories\MomTagFactory
    {
        return \Database\Factories\MomTagFactory::new();
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(MinutesOfMeeting::class, 'mom_tag_mom');
    }
}
