<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'organization_id',
        'provider',
        'model',
        'operation',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'audio_seconds',
        'cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'audio_seconds' => 'float',
            'cost' => 'float',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
