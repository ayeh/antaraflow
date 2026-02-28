<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Jobs;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Notifications\ActionItemOverdueNotification;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckOverdueActionItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $overdueItems = ActionItem::query()
            ->whereNotIn('status', [
                ActionItemStatus::Completed,
                ActionItemStatus::Cancelled,
                ActionItemStatus::CarriedForward,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->with('assignedTo')
            ->get();

        foreach ($overdueItems as $item) {
            if ($item->assignedTo) {
                $item->assignedTo->notify(new ActionItemOverdueNotification($item));
            }
        }
    }
}
