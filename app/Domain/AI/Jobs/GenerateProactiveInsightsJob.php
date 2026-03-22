<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Services\MemoAdvisorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProactiveInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MemoAdvisorService $advisor): void
    {
        $organizations = Organization::query()
            ->where('is_suspended', false)
            ->get();

        foreach ($organizations as $org) {
            $advisor->generateInsights($org->id);
        }
    }
}
