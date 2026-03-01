<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ShareService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function shareWithUser(MinutesOfMeeting $meeting, int $userId, SharePermission $permission, User $sharedBy): MeetingShare
    {
        $share = MeetingShare::query()->create([
            'organization_id' => $meeting->organization_id,
            'minutes_of_meeting_id' => $meeting->id,
            'shared_with_user_id' => $userId,
            'shared_by_user_id' => $sharedBy->id,
            'permission' => $permission,
        ]);

        $this->auditService->log('created', $share);

        return $share->fresh();
    }

    public function generateShareLink(MinutesOfMeeting $meeting, SharePermission $permission, User $sharedBy, ?Carbon $expiresAt = null): MeetingShare
    {
        $share = MeetingShare::query()->create([
            'organization_id' => $meeting->organization_id,
            'minutes_of_meeting_id' => $meeting->id,
            'shared_with_user_id' => null,
            'shared_by_user_id' => $sharedBy->id,
            'permission' => $permission,
            'share_token' => Str::random(64),
            'expires_at' => $expiresAt,
        ]);

        $this->auditService->log('created', $share);

        return $share->fresh();
    }

    public function revokeShare(MeetingShare $share): void
    {
        $this->auditService->log('deleted', $share);
        $share->delete();
    }

    /** @return Collection<int, MeetingShare> */
    public function getSharesForMeeting(MinutesOfMeeting $meeting): Collection
    {
        return MeetingShare::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['sharedWith', 'sharedBy'])
            ->get();
    }
}
