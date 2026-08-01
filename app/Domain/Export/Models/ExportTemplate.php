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
        'blocks',
        'labels',
        'page_setup',
        'glossary',
        'meeting_type',
        'is_system',
        'created_by',
    ];

    protected static function newFactory(): \Database\Factories\ExportTemplateFactory
    {
        return \Database\Factories\ExportTemplateFactory::new();
    }

    /**
     * Bypass the OrganizationScope global scope during route model binding
     * so that system templates (organization_id = null) and templates from
     * other organisations can be resolved — the controller decides access.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        return $this->withoutGlobalScopes()->find($value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'blocks' => 'array',
            'labels' => 'array',
            'page_setup' => 'array',
            'glossary' => 'array',
        ];
    }
}
