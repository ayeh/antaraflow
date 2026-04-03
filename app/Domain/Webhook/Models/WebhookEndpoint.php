<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Models;

use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'description',
        'created_by',
    ];

    /** @var list<string> */
    protected $hidden = ['secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'failure_count' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\WebhookEndpointFactory
    {
        return \Database\Factories\WebhookEndpointFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesToEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    public function recordFailure(): void
    {
        $this->increment('failure_count');

        if ($this->failure_count >= 50) {
            $this->update(['is_active' => false]);
        }
    }

    public function resetFailures(): void
    {
        if ($this->failure_count > 0) {
            $this->update(['failure_count' => 0]);
        }
    }
}
