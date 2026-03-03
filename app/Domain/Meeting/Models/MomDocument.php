<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function minutesOfMeeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
