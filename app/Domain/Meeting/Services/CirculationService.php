<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CirculationService
{
    /**
     * @param  array<int, array{name: string, email: string, mom_attendee_id?: int|null}>  $recipients
     */
    public function circulate(
        MinutesOfMeeting $meeting,
        User $sentBy,
        array $recipients,
        string $subject,
        Carbon $deadline,
        ?string $bodyNote = null,
        int $round = 1,
    ): MomCirculation {
        return DB::transaction(function () use ($meeting, $sentBy, $recipients, $subject, $deadline, $bodyNote, $round): MomCirculation {
            $circulation = MomCirculation::createForOrganization($meeting->organization_id, [
                'minutes_of_meeting_id' => $meeting->id,
                'sent_by' => $sentBy->id,
                'round' => $round,
                'subject' => $subject,
                'body_note' => $bodyNote,
                'deadline_at' => $deadline,
                'status' => 'open',
            ]);

            foreach ($recipients as $recipientData) {
                MomCirculationRecipient::create([
                    'mom_circulation_id' => $circulation->id,
                    'mom_attendee_id' => $recipientData['mom_attendee_id'] ?? null,
                    'name' => $recipientData['name'],
                    'email' => $recipientData['email'],
                    'token' => Str::random(64),
                ]);
            }

            $meeting->update(['status' => MeetingStatus::PendingConfirmation->value]);

            return $circulation->load('recipients');
        });
    }
}
