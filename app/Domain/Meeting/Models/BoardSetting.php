<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Domain\Account\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardSetting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quorum_value' => 'integer',
            'require_chair' => 'boolean',
            'require_secretary' => 'boolean',
            'voting_enabled' => 'boolean',
            'chair_casting_vote' => 'boolean',
            'block_finalization_without_quorum' => 'boolean',
        ];
    }

    protected static function newFactory(): \Database\Factories\BoardSettingFactory
    {
        return \Database\Factories\BoardSettingFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
