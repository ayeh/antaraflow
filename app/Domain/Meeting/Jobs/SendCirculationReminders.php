<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Jobs;

use App\Domain\Meeting\Mail\CirculationReminderMail;
use App\Domain\Meeting\Models\MomCirculation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCirculationReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        MomCirculation::withoutGlobalScopes()
            ->where('status', 'open')
            ->where('deadline_at', '>', now())
            ->where('deadline_at', '<=', now()->addHours(24))
            ->with('recipients')
            ->each(function (MomCirculation $circulation): void {
                foreach ($circulation->recipients as $recipient) {
                    if ($recipient->response !== null) {
                        continue;
                    }

                    Mail::to($recipient->email)->send(
                        new CirculationReminderMail(
                            recipient: $recipient,
                            hasOpened: $recipient->open_count > 0,
                        )
                    );
                }
            });
    }
}
