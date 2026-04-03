<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProactiveInsight extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'type',
        'title',
        'description',
        'severity',
        'metadata',
        'is_read',
        'is_dismissed',
        'generated_at',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ProactiveInsight>  $query
     * @return Builder<ProactiveInsight>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * @param  Builder<ProactiveInsight>  $query
     * @return Builder<ProactiveInsight>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_dismissed', false)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
