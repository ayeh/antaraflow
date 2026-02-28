<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItemHistory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): \Database\Factories\ActionItemHistoryFactory
    {
        return \Database\Factories\ActionItemHistoryFactory::new();
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
