<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Report\Jobs\GenerateReportJob;
use App\Domain\Report\Models\ReportTemplate;
use Cron\CronExpression;
use Illuminate\Console\Command;

class GenerateScheduledReportsCommand extends Command
{
    protected $signature = 'reports:generate-scheduled';

    protected $description = 'Generate reports for active templates that are due based on their schedule';

    public function handle(): int
    {
        $templates = ReportTemplate::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('schedule')
            ->get();

        $dispatched = 0;

        foreach ($templates as $template) {
            if ($this->isDue($template)) {
                GenerateReportJob::dispatch($template);
                $dispatched++;
                $this->info("Dispatched report generation for: {$template->name}");
            }
        }

        $this->info("Dispatched {$dispatched} report(s) for generation.");

        return self::SUCCESS;
    }

    private function isDue(ReportTemplate $template): bool
    {
        try {
            $cron = new CronExpression($template->schedule);

            if (! $template->last_generated_at) {
                return $cron->isDue();
            }

            $nextRun = $cron->getNextRunDate($template->last_generated_at);

            return $nextRun <= now();
        } catch (\Exception) {
            return false;
        }
    }
}
