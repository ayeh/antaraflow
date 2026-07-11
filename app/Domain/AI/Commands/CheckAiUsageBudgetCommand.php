<?php

declare(strict_types=1);

namespace App\Domain\AI\Commands;

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use App\Domain\AI\Services\AiUsageRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CheckAiUsageBudgetCommand extends Command
{
    protected $signature = 'ai:check-budget';

    protected $description = 'Check today\'s AI spend against configured budgets; alert and auto-disable when exceeded';

    public function handle(AiControlService $control, AiUsageRecorder $usage): int
    {
        $spend = $usage->todaySpend();
        $dailyBudget = $control->dailyBudget();
        $hardCap = $control->hardCap();

        $this->info(sprintf('Today\'s AI spend: $%.2f', $spend));

        if ($hardCap > 0 && $spend >= $hardCap) {
            $autoDisabled = $control->isEnabled();
            $control->disable();

            $this->alert(sprintf('Hard cap $%.2f exceeded — AI disabled.', $hardCap));
            $this->dispatchAlert($control, 'critical', $spend, $hardCap, $autoDisabled);

            return self::SUCCESS;
        }

        if ($dailyBudget > 0 && $spend >= $dailyBudget) {
            $this->warn(sprintf('Daily budget $%.2f reached.', $dailyBudget));
            $this->dispatchAlert($control, 'warning', $spend, $dailyBudget, false);
        }

        if ($control->anomalyEnabled()) {
            $baseline = $usage->dailyBaseline(7);
            $multiplier = $control->anomalyMultiplier();
            $threshold = $baseline * $multiplier;

            if ($baseline > 0 && $spend >= $threshold) {
                $this->warn(sprintf('Anomaly: $%.2f is ≥ %.1f× the 7-day baseline ($%.2f).', $spend, $multiplier, $baseline));
                $this->dispatchAlert($control, 'anomaly', $spend, $threshold, false);
            }
        }

        $this->info('Budget check complete.');

        return self::SUCCESS;
    }

    private function dispatchAlert(
        AiControlService $control,
        string $level,
        float $spend,
        float $threshold,
        bool $autoDisabled,
    ): void {
        $cacheKey = "ai_budget_alert_{$level}_".now()->toDateString();

        if (Cache::get($cacheKey)) {
            $this->line('Alert already sent today; skipping.');

            return;
        }

        $email = $control->alertEmail();
        $telegram = $control->alertTelegramChatId();

        if (! $email && ! $telegram) {
            $this->line('No alert recipients configured; skipping notification.');

            return;
        }

        Notification::route('mail', $email)
            ->route('telegram', $telegram)
            ->notify(new AiBudgetAlertNotification($level, $spend, $threshold, $autoDisabled));

        Cache::put($cacheKey, true, now()->endOfDay());

        $this->line('Alert dispatched.');
    }
}
