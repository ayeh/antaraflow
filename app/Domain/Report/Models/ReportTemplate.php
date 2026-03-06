<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Models\User;
use App\Support\Enums\ReportType;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'filters' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\ReportTemplateFactory
    {
        return \Database\Factories\ReportTemplateFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }
}
