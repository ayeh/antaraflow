<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Models\User;
use App\Support\Enums\ExtractionType;
use App\Support\Enums\MeetingType;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'name',
        'meeting_type',
        'extraction_type',
        'prompt_template',
        'system_message',
        'is_active',
        'sort_order',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meeting_type' => MeetingType::class,
            'extraction_type' => ExtractionType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\ExtractionTemplateFactory
    {
        return \Database\Factories\ExtractionTemplateFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Render the prompt template with the given transcript.
     */
    public function renderPrompt(string $transcript): string
    {
        return str_replace('{transcript}', $transcript, $this->prompt_template);
    }
}
