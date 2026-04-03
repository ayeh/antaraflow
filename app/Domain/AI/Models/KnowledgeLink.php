<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Models\User;
use App\Support\Enums\KnowledgeLinkType;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KnowledgeLink extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'link_type',
        'strength',
        'created_by',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'link_type' => KnowledgeLinkType::class,
            'strength' => 'float',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
