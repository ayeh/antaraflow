<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Meeting\Models\BoardSetting;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\ResolutionVote;
use App\Support\Enums\ResolutionStatus;
use App\Support\Enums\VoteChoice;

class ResolutionService
{
    public function create(MinutesOfMeeting $meeting, array $data): MeetingResolution
    {
        $data['meeting_id'] = $meeting->id;
        $data['resolution_number'] = $this->getNextResolutionNumber($meeting->organization_id);
        $data['status'] = $data['status'] ?? ResolutionStatus::Proposed;

        return MeetingResolution::query()->create($data);
    }

    public function update(MeetingResolution $resolution, array $data): MeetingResolution
    {
        $resolution->update($data);

        return $resolution->fresh();
    }

    public function delete(MeetingResolution $resolution): void
    {
        $resolution->delete();
    }

    public function castVote(MeetingResolution $resolution, int $attendeeId, VoteChoice $vote): ResolutionVote
    {
        return ResolutionVote::query()->updateOrCreate(
            [
                'resolution_id' => $resolution->id,
                'attendee_id' => $attendeeId,
            ],
            [
                'vote' => $vote,
                'voted_at' => now(),
            ]
        );
    }

    /**
     * Calculate the result of a resolution based on votes cast.
     */
    public function calculateResult(MeetingResolution $resolution): ResolutionStatus
    {
        $resolution->loadMissing(['votes', 'meeting']);

        $forVotes = $resolution->votes->where('vote', VoteChoice::For)->count();
        $againstVotes = $resolution->votes->where('vote', VoteChoice::Against)->count();

        if ($forVotes > $againstVotes) {
            return ResolutionStatus::Passed;
        }

        if ($againstVotes > $forVotes) {
            return ResolutionStatus::Failed;
        }

        // Tied: check if chair casting vote is enabled
        $boardSetting = BoardSetting::where('organization_id', $resolution->meeting->organization_id)->first();

        if ($boardSetting && $boardSetting->chair_casting_vote) {
            // Chair's casting vote counts as "For" to break the tie
            return ResolutionStatus::Passed;
        }

        // Tie without chair casting vote = Failed
        return ResolutionStatus::Failed;
    }

    /**
     * Generate the next resolution number for an organization.
     */
    public function getNextResolutionNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $prefix = "RES-{$year}-";

        $lastResolution = MeetingResolution::query()
            ->whereHas('meeting', fn ($q) => $q->where('organization_id', $organizationId))
            ->where('resolution_number', 'like', "{$prefix}%")
            ->orderByDesc('resolution_number')
            ->first();

        if ($lastResolution) {
            $lastSequence = (int) str_replace($prefix, '', $lastResolution->resolution_number);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.$nextSequence;
    }
}
