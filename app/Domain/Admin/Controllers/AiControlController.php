<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Services\AiUsageRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AiControlController extends Controller
{
    public function __construct(
        private readonly AiControlService $control,
        private readonly AiUsageRecorder $usage,
    ) {}

    public function index(): View
    {
        $todaySpend = $this->usage->todaySpend();
        $monthSpend = $this->usage->monthSpend();

        $byModel = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('provider, model, SUM(cost) as total_cost, COUNT(*) as calls, SUM(total_tokens) as tokens')
            ->groupBy('provider', 'model')
            ->orderByDesc('total_cost')
            ->get();

        $recentLogs = AiUsageLog::query()
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.ai.index', [
            'enabled' => $this->control->isEnabled(),
            'dailyBudget' => $this->control->dailyBudget(),
            'hardCap' => $this->control->hardCap(),
            'alertEmail' => $this->control->alertEmail(),
            'alertTelegram' => $this->control->alertTelegramChatId(),
            'todaySpend' => $todaySpend,
            'monthSpend' => $monthSpend,
            'byModel' => $byModel,
            'recentLogs' => $recentLogs,
        ]);
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
        ]);

        $this->control->setDailyBudget((float) $validated['daily_budget']);
        $this->control->setHardCap((float) $validated['hard_cap']);
        $this->control->setAlertEmail($validated['alert_email'] ?? null);
        $this->control->setAlertTelegramChatId($validated['alert_telegram_chat_id'] ?? null);

        return redirect()->route('admin.ai.index')->with('success', __('AI budget & alert settings saved.'));
    }
}
