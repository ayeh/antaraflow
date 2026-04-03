<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'structure',
        'default_settings',
        'is_default',
        'is_shared',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'default_settings' => 'array',
            'is_default' => 'boolean',
            'is_shared' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\MeetingTemplateFactory
    {
        return \Database\Factories\MeetingTemplateFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
