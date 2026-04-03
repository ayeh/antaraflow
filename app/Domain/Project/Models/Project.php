<?php

declare(strict_types=1);

namespace App\Domain\Project\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'name',
        'code',
        'description',
        'settings',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\ProjectFactory
    {
        return \Database\Factories\ProjectFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Direct access to the pivot model. Use when you need role or timestamps on the pivot.
     * Prefer members() for User collection access.
     */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(MinutesOfMeeting::class);
    }
}
