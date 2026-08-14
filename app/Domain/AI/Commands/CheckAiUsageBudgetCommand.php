<?php

declare(strict_types=1);

namespace App\Domain\AI\Commands;

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\OrganizationAiBudget;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use App\Domain\AI\Notifications\OrgBudgetWarningNotification;
use App\Domain\AI\Services\AiUsageRecorder;
use App\Domain\AI\Services\OrgBudgetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CheckAiUsageBudgetCommand extends Command
{
    protected $signature = 'ai:check-budget';

    protected $description = 'Check today\'s AI spend against configured budgets; alert and auto-disable when exceeded';

    public function handle(AiControlService $control, AiUsageRecorder $usage, OrgBudgetService $orgBudget): int
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
            $floor = $control->anomalyMinSpend();

            // The floor is not a nicety. The baseline averages in days with no
            // usage, so after a quiet week it sits near zero and any ordinary
            // day clears any multiple of it — on real spend this fired on a
            // fifth of all days at 2x, and still fired at 10x, over amounts
            // under three dollars.
            if ($baseline > 0 && $spend >= $threshold && $spend >= $floor) {
                $this->warn(sprintf('Anomaly: $%.2f is ≥ %.1f× the 7-day baseline ($%.2f).', $spend, $multiplier, $baseline));
                $this->dispatchAlert($control, 'anomaly', $spend, $threshold, false);
            }
        }

        $this->checkOrgBudgets($control, $orgBudget);

        $this->info('Budget check complete.');

        return self::SUCCESS;
    }

    private function checkOrgBudgets(AiControlService $control, OrgBudgetService $orgBudget): void
    {
        $email = $control->alertEmail();
        $telegram = $control->alertTelegramChatId();

        if (! $email && ! $telegram) {
            return;
        }

        OrganizationAiBudget::query()
            ->with('organization')
            ->get()
            ->each(function (OrganizationAiBudget $budget) use ($orgBudget, $email, $telegram): void {
                $utilisation = $orgBudget->utilisation($budget->organization_id);

                if ($utilisation === null) {
                    return;
                }

                $level = match (true) {
                    $utilisation >= 1.0 => 'org_critical',
                    $utilisation >= 0.8 => 'org_warning',
                    default => null,
                };

                if ($level === null) {
                    return;
                }

                $cacheKey = "org_budget_alert_{$budget->organization_id}_{$level}_".now()->toDateString();

                if (Cache::get($cacheKey)) {
                    return;
                }

                $orgName = $budget->organization?->name ?? "Org #{$budget->organization_id}";

                Notification::route('mail', $email)
                    ->route('telegram', $telegram)
                    ->notify(new OrgBudgetWarningNotification($level, $orgName, $utilisation, $budget));

                Cache::put($cacheKey, true, now()->endOfDay());

                $this->warn("Org budget alert [{$level}] dispatched for {$orgName} (utilisation: ".round($utilisation * 100).'%).');
            });
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
