<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelPrice extends Model
{
    protected $fillable = [
        'provider',
        'pattern',
        'is_regex',
        'input_per_mtok',
        'output_per_mtok',
        'cached_input_per_mtok',
        'per_minute',
        'priority',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_regex' => 'boolean',
            'input_per_mtok' => 'float',
            'output_per_mtok' => 'float',
            'cached_input_per_mtok' => 'float',
            'per_minute' => 'float',
            'priority' => 'integer',
        ];
    }
}
