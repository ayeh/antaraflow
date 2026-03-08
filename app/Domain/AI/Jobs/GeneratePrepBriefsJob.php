<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePrepBriefsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public function handle(): void
    {
        $service = app(MeetingPrepBriefService::class);

        $meetings = MinutesOfMeeting::query()
            ->whereBetween('meeting_date', [now(), now()->addDay()])
            ->whereIn('status', [MeetingStatus::Draft, MeetingStatus::InProgress])
            ->has('attendees')
            ->get();

        foreach ($meetings as $meeting) {
            try {
                $briefs = $service->generateForMeeting($meeting);

                foreach ($briefs as $brief) {
                    if ($brief->user) {
                        $brief->user->notify(new MeetingPrepBriefNotification($brief));
                        $brief->update(['email_sent_at' => now()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('GeneratePrepBriefsJob: Failed to generate briefs for meeting', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
