<?php

declare(strict_types=1);

namespace App\Domain\Export\Jobs;

use App\Domain\Export\Mail\MomDistributionMail;
use App\Domain\Export\Models\MomEmailDistribution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMomEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MomEmailDistribution $distribution) {}

    public function handle(): void
    {
        try {
            Mail::to($this->distribution->recipients)
                ->send(new MomDistributionMail($this->distribution));

            $this->distribution->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $this->distribution->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
