<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use App\Domain\AI\Services\AiUsageRecorder;
use App\Domain\AI\Services\AnthropicBillingService;
use App\Domain\AI\Services\OpenAiBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class AiControlController extends Controller
{
    public function __construct(
        private readonly AiControlService $control,
        private readonly AiUsageRecorder $usage,
        private readonly OpenAiBillingService $billing,
        private readonly AnthropicBillingService $anthropicBilling,
    ) {}

    public function index(Request $request): View
    {
        $todaySpend = $this->usage->todaySpend();
        $monthSpend = $this->usage->monthSpend();

        $monthStart = now()->startOfMonth();

        // Optional drill-down filters applied to the monthly breakdowns.
        $provider = $request->query('provider') ?: null;
        $feature = $request->query('feature') ?: null;
        $scoped = fn () => AiUsageLog::query()
            ->where('created_at', '>=', $monthStart)
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->when($feature, fn ($q) => $q->where('feature', $feature));

        $providerOptions = AiUsageLog::query()->distinct()->orderBy('provider')->pluck('provider')->filter()->values();
        $featureOptions = AiUsageLog::query()->distinct()->orderBy('feature')->pluck('feature')->filter()->values();

        $byModel = $scoped()
            ->selectRaw('provider, model, SUM(cost) as total_cost, COUNT(*) as calls, SUM(total_tokens) as tokens')
            ->groupBy('provider', 'model')
            ->orderByDesc('total_cost')
            ->get();

        $byFeature = $scoped()
            ->selectRaw('feature, SUM(cost) as total_cost, COUNT(*) as calls')
            ->groupBy('feature')
            ->orderByDesc('total_cost')
            ->get();

        $topSessions = $scoped()
            ->whereNotNull('session_id')
            ->selectRaw('session_id, MAX(feature) as feature, COUNT(*) as calls, SUM(total_tokens) as tokens, SUM(cost) as total_cost')
            ->groupBy('session_id')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get();

        // Health metrics are an overall monthly summary (not filtered).
        $monthly = fn () => AiUsageLog::query()->where('created_at', '>=', $monthStart);
        $totalCalls = $monthly()->count();
        $errorCalls = $monthly()->where('status', 'error')->count();
        $promptTokenSum = (int) $monthly()->sum('prompt_tokens');
        $cachedTokenSum = (int) $monthly()->sum('cached_tokens');

        $errorRate = $totalCalls > 0 ? $errorCalls / $totalCalls : 0.0;
        $cacheHitRate = $promptTokenSum > 0 ? $cachedTokenSum / $promptTokenSum : 0.0;

        // Latency percentiles (p50/p95/p99) computed in PHP for cross-DB portability.
        $durations = $monthly()->where('status', 'success')->whereNotNull('duration_ms')->orderBy('duration_ms')->pluck('duration_ms')->all();
        $percentile = function (int $p) use ($durations): int {
            if ($durations === []) {
                return 0;
            }

            return (int) $durations[(int) floor(($p / 100) * (count($durations) - 1))];
        };
        $latency = ['p50' => $percentile(50), 'p95' => $percentile(95), 'p99' => $percentile(99)];

        $dailySeries = $this->usage->dailySeries(30);
        $dailyBaseline = $this->usage->dailyBaseline(7);

        $recentLogs = AiUsageLog::query()
            ->latest()
            ->limit(20)
            ->get();

        $creditTopup = $this->control->creditTopup();
        $creditTopupDate = $this->control->creditTopupDate();

        $default = config('ai.default', 'openai');
        $apiKeys = [
            ['label' => 'OpenAI — inference', 'env' => 'OPENAI_API_KEY', 'masked' => $this->maskKey(config('ai.providers.openai.api_key')), 'active' => $default === 'openai'],
            ['label' => 'OpenAI — Admin / billing', 'env' => 'OPENAI_ADMIN_KEY', 'masked' => $this->maskKey(config('ai.openai_admin_key')), 'active' => false],
            ['label' => 'Anthropic (Claude)', 'env' => 'ANTHROPIC_API_KEY', 'masked' => $this->maskKey(config('ai.providers.anthropic.api_key')), 'active' => $default === 'anthropic'],
            ['label' => 'Anthropic — Admin / billing', 'env' => 'ANTHROPIC_ADMIN_KEY', 'masked' => $this->maskKey(config('ai.anthropic_admin_key')), 'active' => false],
            ['label' => 'Google (Gemini)', 'env' => 'GOOGLE_AI_API_KEY', 'masked' => $this->maskKey(config('ai.providers.google.api_key')), 'active' => $default === 'google'],
            ['label' => 'Telegram bot', 'env' => 'TELEGRAM_BOT_TOKEN', 'masked' => $this->maskKey(config('services.telegram.bot_token')), 'active' => false],
        ];
        $activeProvider = $default;
        $activeModel = config("ai.providers.{$default}.model");

        $openAiConfigured = $this->billing->isConfigured();
        $openAiMonthCost = $openAiConfigured ? $this->billing->monthCost() : null;
        $openAiProjectScoped = $this->billing->projectId() !== null;

        $anthropicConfigured = $this->anthropicBilling->isConfigured();
        $anthropicMonthCost = $anthropicConfigured ? $this->anthropicBilling->monthCost() : null;

        $estimatedBalance = null;
        if ($openAiConfigured && $creditTopup > 0 && $creditTopupDate) {
            $spentSinceTopup = $this->billing->costSince(\Illuminate\Support\Carbon::parse($creditTopupDate)->startOfDay());
            if ($spentSinceTopup !== null) {
                $estimatedBalance = round($creditTopup - $spentSinceTopup, 2);
            }
        }

        return view('admin.ai.index', [
            'enabled' => $this->control->isEnabled(),
            'dailyBudget' => $this->control->dailyBudget(),
            'hardCap' => $this->control->hardCap(),
            'alertEmail' => $this->control->alertEmail(),
            'alertTelegram' => $this->control->alertTelegramChatId(),
            'todaySpend' => $todaySpend,
            'monthSpend' => $monthSpend,
            'byModel' => $byModel,
            'byFeature' => $byFeature,
            'topSessions' => $topSessions,
            'recentLogs' => $recentLogs,
            'latency' => $latency,
            'errorRate' => $errorRate,
            'cacheHitRate' => $cacheHitRate,
            'dailySeries' => $dailySeries,
            'dailyBaseline' => $dailyBaseline,
            'providerOptions' => $providerOptions,
            'featureOptions' => $featureOptions,
            'selectedProvider' => $provider,
            'selectedFeature' => $feature,
            'creditTopup' => $creditTopup,
            'creditTopupDate' => $creditTopupDate,
            'anomalyEnabled' => $this->control->anomalyEnabled(),
            'anomalyMultiplier' => $this->control->anomalyMultiplier(),
            'openAiConfigured' => $openAiConfigured,
            'openAiMonthCost' => $openAiMonthCost,
            'openAiProjectScoped' => $openAiProjectScoped,
            'estimatedBalance' => $estimatedBalance,
            'anthropicConfigured' => $anthropicConfigured,
            'anthropicMonthCost' => $anthropicMonthCost,
            'apiKeys' => $apiKeys,
            'activeProvider' => $activeProvider,
            'activeModel' => $activeModel,
        ]);
    }

    /**
     * Mask a secret for display: keep a short prefix + last 4 chars only.
     * Never returns the full key.
     */
    private function maskKey(mixed $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        $length = strlen($key);

        if ($length <= 14) {
            return substr($key, 0, 4).str_repeat('•', max(1, $length - 4));
        }

        return substr($key, 0, 10).'…'.substr($key, -4);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->control->setEnabled($request->boolean('enabled'));

        return redirect()->route('admin.ai.index')->with(
            'success',
            $this->control->isEnabled()
                ? __('AI features enabled.')
                : __('AI features disabled. All AI API calls are now blocked.'),
        );
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_budget' => ['required', 'numeric', 'min:0'],
            'hard_cap' => ['required', 'numeric', 'min:0'],
            'alert_email' => ['nullable', 'email', 'max:255'],
            'alert_telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'credit_topup' => ['required', 'numeric', 'min:0'],
            'credit_topup_date' => ['nullable', 'date'],
            'anomaly_multiplier' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $this->control->setDailyBudget((float) $validated['daily_budget']);
        $this->control->setHardCap((float) $validated['hard_cap']);
        $this->control->setAlertEmail($validated['alert_email'] ?? null);
        $this->control->setAlertTelegramChatId($validated['alert_telegram_chat_id'] ?? null);
        $this->control->setCreditTopup((float) $validated['credit_topup'], $validated['credit_topup_date'] ?? null);
        $this->control->setAnomalyEnabled($request->boolean('anomaly_enabled'));
        $this->control->setAnomalyMultiplier((float) $validated['anomaly_multiplier']);

        return redirect()->route('admin.ai.index')->with('success', __('AI budget & alert settings saved.'));
    }

    public function sendTest(): RedirectResponse
    {
        $email = $this->control->alertEmail();
        $telegram = $this->control->alertTelegramChatId();

        if (! $email && ! $telegram) {
            return redirect()->route('admin.ai.index')
                ->with('error', __('Set an alert email or Telegram chat ID first, then save.'));
        }

        if ($telegram && ! config('services.telegram.bot_token')) {
            return redirect()->route('admin.ai.index')
                ->with('error', __('TELEGRAM_BOT_TOKEN is not configured on the server; Telegram cannot be tested yet.'));
        }

        Notification::route('mail', $email)
            ->route('telegram', $telegram)
            ->notify(new AiBudgetAlertNotification('warning', $this->usage->todaySpend(), $this->control->dailyBudget()));

        return redirect()->route('admin.ai.index')
            ->with('success', __('Test alert sent to the configured channels.'));
    }
}
