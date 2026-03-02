<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(
        private readonly VersionService $versionService,
        private readonly AuditService $auditService,
        private readonly MomNumberService $momNumberService,
    ) {}

    public function create(array $data, User $user): MinutesOfMeeting
    {
        $tags = $data['tags'] ?? null;
        $allowExternalJoin = $data['allow_external_join'] ?? false;
        $requireRsvp = $data['require_rsvp'] ?? false;
        // Defaults to true; form submissions always supply a value via hidden input,
        // so this fallback applies only to non-form callers (e.g. programmatic/API use).
        $autoNotify = $data['auto_notify'] ?? true;

        unset($data['tags'], $data['allow_external_join'], $data['require_rsvp'], $data['auto_notify']);

        $data['created_by'] = $user->id;
        $data['organization_id'] = $user->current_organization_id;
        $data['status'] = MeetingStatus::Draft;
        $data['mom_number'] = $this->momNumberService->generate($user->current_organization_id);

        return DB::transaction(function () use ($data, $tags, $allowExternalJoin, $requireRsvp, $autoNotify) {
            $mom = MinutesOfMeeting::query()->create($data);

            if ($tags !== null) {
                $mom->tags()->sync($tags);
            }

            $mom->joinSetting()->create([
                'allow_external_join' => $allowExternalJoin,
                'require_rsvp' => $requireRsvp,
                'auto_notify' => $autoNotify,
            ]);

            $this->auditService->log('created', $mom);

            return $mom;
        });
    }

    public function update(MinutesOfMeeting $mom, array $data): MinutesOfMeeting
    {
        if ($mom->status === MeetingStatus::Approved) {
            throw new \DomainException('Cannot edit an approved meeting.');
        }

        $tags = array_key_exists('tags', $data) ? $data['tags'] : false;
        $allowExternalJoin = $data['allow_external_join'] ?? false;
        $requireRsvp = $data['require_rsvp'] ?? false;
        // Defaults to true; form submissions always supply a value via hidden input,
        // so this fallback applies only to non-form callers (e.g. programmatic/API use).
        $autoNotify = $data['auto_notify'] ?? true;

        unset($data['tags'], $data['allow_external_join'], $data['require_rsvp'], $data['auto_notify']);

        return DB::transaction(function () use ($mom, $data, $tags, $allowExternalJoin, $requireRsvp, $autoNotify) {
            $oldValues = $mom->only(array_keys($data));
            $mom->update($data);

            if ($tags !== false) {
                $mom->tags()->sync($tags ?? []);
            }

            $mom->joinSetting()->updateOrCreate(
                [],
                [
                    'allow_external_join' => $allowExternalJoin,
                    'require_rsvp' => $requireRsvp,
                    'auto_notify' => $autoNotify,
                ]
            );

            $this->auditService->log('updated', $mom, $oldValues, $data);

            return $mom->fresh();
        });
    }

    public function finalize(MinutesOfMeeting $mom, User $user): MinutesOfMeeting
    {
        if (! in_array($mom->status, [MeetingStatus::Draft, MeetingStatus::InProgress])) {
            throw new \DomainException('Only draft or in-progress meetings can be finalized.');
        }

        $this->versionService->createVersion($mom, $user, 'Meeting finalized');

        $mom->update(['status' => MeetingStatus::Finalized]);
        $this->auditService->log('finalized', $mom);

        return $mom->fresh();
    }

    public function approve(MinutesOfMeeting $mom, User $user): MinutesOfMeeting
    {
        if ($mom->status !== MeetingStatus::Finalized) {
            throw new \DomainException('Only finalized meetings can be approved.');
        }

        $mom->update(['status' => MeetingStatus::Approved]);
        $this->auditService->log('approved', $mom);

        return $mom->fresh();
    }

    public function revertToDraft(MinutesOfMeeting $mom, User $user): MinutesOfMeeting
    {
        if ($mom->status !== MeetingStatus::Finalized) {
            throw new \DomainException('Only finalized meetings can be reverted to draft.');
        }

        $this->versionService->createVersion($mom, $user, 'Reverted to draft');

        $mom->update(['status' => MeetingStatus::Draft]);
        $this->auditService->log('reverted_to_draft', $mom);

        return $mom->fresh();
    }

    public function delete(MinutesOfMeeting $mom): void
    {
        $this->auditService->log('deleted', $mom);
        $mom->delete();
    }
}
