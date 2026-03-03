<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SmtpConfiguration extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = Crypt::encryptString($value);
    }

    public function getDecryptedUsernameAttribute(): string
    {
        return Crypt::decryptString($this->attributes['username']);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getDecryptedPasswordAttribute(): string
    {
        return Crypt::decryptString($this->attributes['password']);
    }

    public function isGlobal(): bool
    {
        return $this->organization_id === null;
    }

    public static function getForOrganization(int $organizationId): ?self
    {
        return static::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->first()
            ?? static::query()
                ->whereNull('organization_id')
                ->where('is_active', true)
                ->first();
    }
}
