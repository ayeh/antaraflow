<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OrganizationInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'email',
        'role',
        'token',
        'invited_by_user_id',
        'expires_at',
        'accepted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\OrganizationInvitationFactory
    {
        return \Database\Factories\OrganizationInvitationFactory::new();
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /** @param  Builder<OrganizationInvitation>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', Carbon::now());
    }
}
