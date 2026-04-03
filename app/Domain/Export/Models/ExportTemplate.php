<?php

declare(strict_types=1);

namespace App\Domain\Export\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportTemplate extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'header_html',
        'footer_html',
        'css_overrides',
        'logo_path',
        'primary_color',
        'font_family',
        'is_default',
    ];

    protected static function newFactory(): \Database\Factories\ExportTemplateFactory
    {
        return \Database\Factories\ExportTemplateFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
