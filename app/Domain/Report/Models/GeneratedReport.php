<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\GeneratedReportFactory
    {
        return \Database\Factories\GeneratedReportFactory::new();
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }
}
