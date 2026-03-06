<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\BoardSetting;
use App\Domain\Meeting\Models\MinutesOfMeeting;

class QuorumService
{
    /**
     * Check the quorum status for a meeting.
     *
     * @return array{is_met: bool, required: int, present: int, type: string}
     */
    public function check(MinutesOfMeeting $meeting): array
    {
        $meeting->loadMissing(['attendees']);

        $totalAttendees = $meeting->attendees->count();
        $presentAttendees = $meeting->attendees->where('is_present', true)->count();

        $boardSetting = BoardSetting::where('organization_id', $meeting->organization_id)->first();

        if (! $boardSetting) {
            return [
                'is_met' => true,
                'required' => 0,
                'present' => $presentAttendees,
                'type' => 'none',
            ];
        }

        $required = $this->calculateRequired($boardSetting, $totalAttendees);

        return [
            'is_met' => $presentAttendees >= $required,
            'required' => $required,
            'present' => $presentAttendees,
            'type' => $boardSetting->quorum_type,
        ];
    }

    /**
     * Calculate whether quorum is met for the given attendee counts.
     */
    public function calculate(Organization $org, int $totalAttendees, int $presentAttendees): bool
    {
        $boardSetting = BoardSetting::where('organization_id', $org->id)->first();

        if (! $boardSetting) {
            return true;
        }

        $required = $this->calculateRequired($boardSetting, $totalAttendees);

        return $presentAttendees >= $required;
    }

    /**
     * Check whether quorum is met for a specific meeting.
     */
    public function isQuorumMet(MinutesOfMeeting $meeting): bool
    {
        return $this->check($meeting)['is_met'];
    }

    /**
     * Calculate the required number of attendees for quorum.
     */
    private function calculateRequired(BoardSetting $boardSetting, int $totalAttendees): int
    {
        if ($boardSetting->quorum_type === 'count') {
            return $boardSetting->quorum_value;
        }

        // percentage
        return (int) ceil($totalAttendees * $boardSetting->quorum_value / 100);
    }
}
