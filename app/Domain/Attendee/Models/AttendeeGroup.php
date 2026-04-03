<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendeeGroup extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_members',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_members' => 'array',
        ];
    }

    protected static function newFactory(): \Database\Factories\AttendeeGroupFactory
    {
        return \Database\Factories\AttendeeGroupFactory::new();
    }
}
