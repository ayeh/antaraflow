<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Models;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomAiConversation;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Attendee\Models\MomJoinSetting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MinutesOfMeeting extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'datetime',
            'metadata' => 'array',
            'status' => MeetingStatus::class,
        ];
    }

    protected static function newFactory(): \Database\Factories\MinutesOfMeetingFactory
    {
        return \Database\Factories\MinutesOfMeetingFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MeetingTemplate::class, 'meeting_template_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MomVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(MomVersion::class)->latestOfMany('version_number');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MomTag::class, 'mom_tag_mom');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(MomInput::class);
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(AudioTranscription::class);
    }

    public function manualNotes(): HasMany
    {
        return $this->hasMany(MomManualNote::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(MomExtraction::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(MomTopic::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(MomAiConversation::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MomAttendee::class);
    }

    public function joinSetting(): HasOne
    {
        return $this->hasOne(MomJoinSetting::class);
    }
}
