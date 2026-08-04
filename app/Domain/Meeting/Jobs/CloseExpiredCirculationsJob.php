<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Jobs;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Models\MomCirculation;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CloseExpiredCirculationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        MomCirculation::withoutGlobalScopes()
            ->where('status', 'open')
            ->where('deadline_at', '<=', now())
            ->with(['recipients', 'meeting'])
            ->each(fn (MomCirculation $circulation) => $this->process($circulation));
    }

    private function process(MomCirculation $circulation): void
    {
        $holdReason = $this->holdReason($circulation);

        if ($holdReason !== null) {
            $circulation->update([
                'status' => 'awaiting_secretary',
            ]);

            // TODO Task 18: schedule + notify secretary
            return;
        }

        DB::transaction(function () use ($circulation): void {
            $now = now();

            // Mark silent recipients (opened but never responded) as deemed confirmed
            $circulation->recipients()
                ->whereNull('response')
                ->where('open_count', '>', 0)
                ->update(['deemed_confirmed_at' => $now]);

            $circulation->update([
                'status' => 'closed_approved',
                'closed_at' => $now,
            ]);

            $meeting = $circulation->meeting;
            $meeting->update(['status' => MeetingStatus::Approved->value]);

            MeetingApproved::dispatch($meeting, null, $circulation);
        });
    }

    private function holdReason(MomCirculation $circulation): ?string
    {
        // Hold: nobody opened the MOM — silence from nobody who read it cannot be consent
        if ($circulation->recipients->every(fn ($r) => $r->open_count === 0)) {
            return 'nobody_opened';
        }

        // Hold: unresolved amendment requests
        $hasUnresolved = Comment::withoutGlobalScopes()
            ->whereIn('mom_circulation_recipient_id', $circulation->recipients->pluck('id'))
            ->whereNull('resolved_at')
            ->exists();

        if ($hasUnresolved) {
            return 'unresolved_amendments';
        }

        return null;
    }
}
