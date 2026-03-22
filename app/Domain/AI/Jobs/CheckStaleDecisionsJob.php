<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Notifications\StaleDecisionNotification;
use App\Domain\AI\Services\DecisionTrackerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckStaleDecisionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DecisionTrackerService $tracker): void
    {
        $organizations = Organization::query()
            ->where('is_suspended', false)
            ->get();

        foreach ($organizations as $org) {
            $staleDecisions = $tracker->getStaleDecisions($org->id);

            if ($staleDecisions->isEmpty()) {
                continue;
            }

            $grouped = $staleDecisions->groupBy(fn ($d) => $d['meeting']->id);

            foreach ($grouped as $meetingDecisions) {
                $meeting = $meetingDecisions->first()['meeting'];
                $creator = $meeting->createdBy;

                if (! $creator) {
                    continue;
                }

                $creator->notify(new StaleDecisionNotification(
                    $meeting,
                    $meetingDecisions->toArray(),
                ));
            }
        }
    }
}
