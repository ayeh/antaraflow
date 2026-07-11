@extends('admin.layouts.app')

@section('title', __('AI Control'))
@section('page-title', __('AI Control'))

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">{{ __('Dashboard') }}</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">{{ __('AI Control') }}</span>
    </nav>
@endsection

@section('content')
    <div class="max-w-5xl space-y-8">
        {{-- Master toggle --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between gap-6 flex-wrap">
                <div>
                    <h3 class="text-lg font-semibold text-white mb-1">{{ __('AI Features') }}</h3>
                    <p class="text-sm text-slate-400">
                        {{ __('Master switch for all AI API calls (extraction, MOM generation, transcription, search).') }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium
                        {{ $enabled ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400' }}">
                        <span class="size-2 rounded-full {{ $enabled ? 'bg-green-400' : 'bg-red-400' }}"></span>
                        {{ $enabled ? __('ENABLED') : __('DISABLED') }}
                    </span>
                    <form method="POST" action="{{ route('admin.ai.toggle') }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
                        <button type="submit"
                                class="px-6 py-2.5 text-sm font-medium rounded-lg transition-colors text-white
                                {{ $enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }}">
                            {{ $enabled ? __('Turn OFF') : __('Turn ON') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Spend snapshot --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Spend Today') }}</p>
                <p class="text-3xl font-bold text-white">${{ number_format($todaySpend, 2) }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Spend This Month') }}</p>
                <p class="text-3xl font-bold text-white">${{ number_format($monthSpend, 2) }}</p>
                @php
                    $spark = array_slice(array_values($dailySeries), -7);
                    $sMax = max(0.0001, max($spark));
                    $sN = max(1, count($spark) - 1);
                    $pts = [];
                    foreach ($spark as $i => $v) { $pts[] = round($i * (100 / $sN), 1).','.round(24 - ($v / $sMax) * 22, 1); }
                @endphp
                <svg viewBox="0 0 100 26" preserveAspectRatio="none" class="w-full h-6 mt-2" aria-hidden="true">
                    <polyline fill="none" stroke="#3b82f6" stroke-width="2" points="{{ implode(' ', $pts) }}"/>
                </svg>
                <p class="text-xs text-slate-500 mt-1">{{ __('Last 7 days') }}</p>
            </div>
        </div>

        {{-- Daily spend chart (30 days) with anomaly highlighting --}}
        @php
            $vals = array_values($dailySeries);
            $days = array_keys($dailySeries);
            $n = max(1, count($vals));
            $threshold = $dailyBaseline * $anomalyMultiplier;
            $maxV = max(0.0001, max($vals), $threshold);
            $chartW = 680; $chartH = 150; $slot = $chartW / $n; $barW = max(2, $slot * 0.68);
            $yBaseline = $chartH - ($dailyBaseline / $maxV) * $chartH;
            $yThreshold = $chartH - ($threshold / $maxV) * $chartH;
        @endphp
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                <h3 class="text-lg font-semibold text-white">{{ __('Daily Spend — last 30 days') }}</h3>
                <div class="flex items-center gap-4 text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-2 rounded-sm" style="background:#3b82f6"></span>{{ __('Daily') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-2 rounded-sm" style="background:#ef4444"></span>{{ __('Anomaly') }}</span>
                    @if($dailyBaseline > 0)<span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 border-t border-dashed" style="border-color:#f59e0b"></span>{{ __('Threshold') }}</span>@endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 {{ $chartW }} {{ $chartH + 22 }}" class="w-full" style="min-width:560px" role="img" aria-label="{{ __('Daily AI spend over the last 30 days') }}">
                    @if($dailyBaseline > 0)
                        <line x1="0" y1="{{ $yBaseline }}" x2="{{ $chartW }}" y2="{{ $yBaseline }}" stroke="#64748b" stroke-width="1" stroke-opacity="0.6"/>
                        <line x1="0" y1="{{ $yThreshold }}" x2="{{ $chartW }}" y2="{{ $yThreshold }}" stroke="#f59e0b" stroke-width="1" stroke-dasharray="4 3"/>
                    @endif
                    @foreach($vals as $i => $v)
                        @php
                            $h = ($v / $maxV) * $chartH;
                            $x = $i * $slot + ($slot - $barW) / 2;
                            $y = $chartH - $h;
                            $isAnomaly = $dailyBaseline > 0 && $v > 0 && $v >= $threshold;
                        @endphp
                        <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($barW, 1) }}" height="{{ round(max(0, $h), 1) }}" rx="1"
                              fill="{{ $isAnomaly ? '#ef4444' : '#3b82f6' }}" fill-opacity="{{ $v > 0 ? '0.9' : '0.25' }}">
                            <title>{{ $days[$i] }}: ${{ number_format($v, 4) }}</title>
                        </rect>
                    @endforeach
                    <text x="0" y="{{ $chartH + 16 }}" fill="#64748b" font-size="11" font-family="monospace">{{ \Illuminate\Support\Str::of($days[0])->substr(5) }}</text>
                    <text x="{{ $chartW }}" y="{{ $chartH + 16 }}" fill="#64748b" font-size="11" font-family="monospace" text-anchor="end">{{ __('today') }}</text>
                </svg>
            </div>
            <p class="text-xs text-slate-500 mt-2">{{ __('Bars turn red when a day meets the anomaly threshold (baseline × multiplier). Hover a bar for the exact amount.') }}</p>
        </div>

        {{-- Health metrics (this month) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Latency (ms)') }}</p>
                <p class="text-2xl font-bold text-white" style="font-variant-numeric:tabular-nums">
                    {{ number_format($latency['p50']) }}<span class="text-slate-500 text-base font-normal"> · {{ number_format($latency['p95']) }} · {{ number_format($latency['p99']) }}</span>
                </p>
                <p class="text-xs text-slate-500 mt-1">{{ __('p50 · p95 · p99, this month') }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Error rate') }}</p>
                <p class="text-3xl font-bold {{ $errorRate > 0.05 ? 'text-red-400' : 'text-white' }}">{{ number_format($errorRate * 100, 1) }}%</p>
                <p class="text-xs text-slate-500 mt-1">{{ __('Failed AI calls, this month') }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Cache-hit rate') }}</p>
                <p class="text-3xl font-bold text-white">{{ number_format($cacheHitRate * 100, 1) }}%</p>
                <p class="text-xs text-slate-500 mt-1">{{ __('Cached prompt tokens') }}</p>
            </div>
        </div>

        {{-- OpenAI actual (Admin API) --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">{{ __('OpenAI Account (live)') }}</h3>
                <a href="https://platform.openai.com/usage" target="_blank" rel="noopener"
                   class="text-xs text-blue-400 hover:text-blue-300">{{ __('Open OpenAI usage ↗') }}</a>
            </div>
            @if(! $openAiConfigured)
                <p class="text-sm text-slate-400">
                    {{ __('Set OPENAI_ADMIN_KEY (an sk-admin-… key) on the server to pull real spend from OpenAI\'s Costs API.') }}
                </p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-slate-400 mb-1">{{ __('Actual spend this month (OpenAI)') }}</p>
                        <p class="text-3xl font-bold text-white">
                            {{ $openAiMonthCost === null ? '—' : '$'.number_format($openAiMonthCost, 2) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-400 mb-1">{{ __('Estimated balance remaining') }}</p>
                        @if($estimatedBalance === null)
                            <p class="text-3xl font-bold text-slate-500">—</p>
                            <p class="text-xs text-slate-500 mt-1">{{ __('Enter your last top-up amount & date below.') }}</p>
                        @else
                            <p class="text-3xl font-bold {{ $estimatedBalance <= 0 ? 'text-red-400' : 'text-green-400' }}">
                                ${{ number_format($estimatedBalance, 2) }}
                            </p>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ __('Top-up $:topup on :date − actual spend since', ['topup' => number_format($creditTopup, 2), 'date' => $creditTopupDate]) }}
                            </p>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-4">
                    {{ __('OpenAI exposes no true balance endpoint; balance is estimated. The exact figure is on OpenAI\'s dashboard.') }}
                </p>
            @endif
        </div>

        {{-- Other providers — actual spend --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ __('Other Providers — Actual Spend') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-slate-400 mb-1">{{ __('Anthropic (Claude), this month') }}</p>
                    @if(! $anthropicConfigured)
                        <p class="text-lg text-slate-500">{{ __('Set ANTHROPIC_ADMIN_KEY (sk-ant-admin-…)') }}</p>
                    @else
                        <p class="text-3xl font-bold text-white">{{ $anthropicMonthCost === null ? '—' : '$'.number_format($anthropicMonthCost, 2) }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ __('From the Cost Report API') }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm text-slate-400 mb-1">{{ __('Google (Gemini), this month') }}</p>
                    <p class="text-lg text-slate-500">{{ __('Estimate only') }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ __('Google has no simple costs API; actuals live in GCP billing (BigQuery export).') }}
                        <a href="https://console.cloud.google.com/billing" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">{{ __('Open GCP billing ↗') }}</a>
                    </p>
                </div>
            </div>
        </div>

        {{-- API keys in use --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-lg font-semibold text-white">{{ __('API Keys in Use') }}</h3>
                <span class="text-xs text-slate-400">
                    {{ __('Active') }}: <span class="text-slate-200 font-medium">{{ $activeProvider }}</span>
                    @if($activeModel)<span class="font-mono text-xs">· {{ $activeModel }}</span>@endif
                </span>
            </div>
            <p class="text-xs text-slate-500 mb-4">{{ __('Masked for safety — prefix + last 4 only. Full keys live in the server .env.') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Purpose') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Env var') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Key') }}</th>
                            <th class="py-2 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($apiKeys as $key)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4">
                                    {{ $key['label'] }}
                                    @if($key['active'])
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-500/15 text-green-400">{{ __('ACTIVE') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 font-mono text-xs text-slate-400">{{ $key['env'] }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $key['masked'] ?? '—' }}</td>
                                <td class="py-2">
                                    @if($key['masked'])
                                        <span class="inline-flex items-center gap-1.5 text-green-400 text-xs"><span class="size-1.5 rounded-full bg-green-400"></span>{{ __('Set') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-slate-500 text-xs"><span class="size-1.5 rounded-full bg-slate-600"></span>{{ __('Not set') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500 mt-4">{{ __('Per-organization keys are managed separately under each org\'s AI Provider settings.') }}</p>
        </div>

        {{-- Budget & alerts --}}
        <form method="POST" action="{{ route('admin.ai.update-settings') }}">
            @csrf
            @method('PUT')
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-white mb-1">{{ __('Budget & Alerts') }}</h3>
                    <p class="text-sm text-slate-400">
                        {{ __('Checked hourly. Set 0 to disable a threshold. The hard cap auto-disables AI when exceeded.') }}
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="daily_budget" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Daily budget — alert (USD)') }}</label>
                        <input type="number" step="0.01" min="0" name="daily_budget" id="daily_budget"
                               value="{{ old('daily_budget', $dailyBudget) }}"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('daily_budget') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="hard_cap" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Hard cap — auto-disable (USD)') }}</label>
                        <input type="number" step="0.01" min="0" name="hard_cap" id="hard_cap"
                               value="{{ old('hard_cap', $hardCap) }}"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('hard_cap') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alert_email" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Alert email') }}</label>
                        <input type="email" name="alert_email" id="alert_email"
                               value="{{ old('alert_email', $alertEmail) }}"
                               placeholder="ops@example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('alert_email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alert_telegram_chat_id" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Alert Telegram chat ID') }}</label>
                        <input type="text" name="alert_telegram_chat_id" id="alert_telegram_chat_id"
                               value="{{ old('alert_telegram_chat_id', $alertTelegram) }}"
                               placeholder="-1001234567890"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('alert_telegram_chat_id') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">{{ __('Requires TELEGRAM_BOT_TOKEN in the environment.') }}</p>
                    </div>
                    <div>
                        <label for="credit_topup" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Last top-up amount (USD)') }}</label>
                        <input type="number" step="0.01" min="0" name="credit_topup" id="credit_topup"
                               value="{{ old('credit_topup', $creditTopup) }}"
                               placeholder="100.00"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('credit_topup') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">{{ __('Used to estimate remaining balance vs OpenAI actual spend.') }}</p>
                    </div>
                    <div>
                        <label for="credit_topup_date" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Top-up date') }}</label>
                        <input type="date" name="credit_topup_date" id="credit_topup_date"
                               value="{{ old('credit_topup_date', $creditTopupDate) }}"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('credit_topup_date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="border-t border-slate-700 pt-5">
                    <label class="flex items-center gap-3 cursor-pointer mb-3">
                        <input type="hidden" name="anomaly_enabled" value="0">
                        <input type="checkbox" name="anomaly_enabled" value="1" @checked($anomalyEnabled)
                               class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500">
                        <span class="text-sm text-slate-300">{{ __('Enable spend anomaly detection (rolling 7-day baseline)') }}</span>
                    </label>
                    <div class="max-w-xs">
                        <label for="anomaly_multiplier" class="block text-sm font-medium text-slate-300 mb-1">{{ __('Anomaly multiplier (× baseline)') }}</label>
                        <input type="number" step="0.1" min="1" max="100" name="anomaly_multiplier" id="anomaly_multiplier"
                               value="{{ old('anomaly_multiplier', $anomalyMultiplier) }}"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('anomaly_multiplier') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">{{ __('Alert when today\'s spend ≥ this many times the 7-day daily average.') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                        {{ __('Save Settings') }}
                    </button>
                    <button type="submit" form="ai-test-alert-form"
                            class="px-6 py-2.5 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                        {{ __('Send Test Alert') }}
                    </button>
                </div>
                <p class="text-xs text-slate-500">{{ __('Test uses the saved email & Telegram chat ID. Save first if you just changed them.') }}</p>
            </div>
        </form>

        <form id="ai-test-alert-form" method="POST" action="{{ route('admin.ai.test-alert') }}" class="hidden">
            @csrf
        </form>

        {{-- Filter bar (scopes the breakdowns below) --}}
        <form method="GET" action="{{ route('admin.ai.index') }}" class="bg-slate-800 border border-slate-700 rounded-xl p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('Provider') }}</label>
                <select name="provider" class="bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-1.5 text-sm">
                    <option value="">{{ __('All providers') }}</option>
                    @foreach($providerOptions as $p)<option value="{{ $p }}" @selected($selectedProvider === $p)>{{ $p }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">{{ __('Feature') }}</label>
                <select name="feature" class="bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-1.5 text-sm">
                    <option value="">{{ __('All features') }}</option>
                    @foreach($featureOptions as $f)<option value="{{ $f }}" @selected($selectedFeature === $f)>{{ $f }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">{{ __('Filter') }}</button>
            @if($selectedProvider || $selectedFeature)
                <a href="{{ route('admin.ai.index') }}" class="px-4 py-1.5 text-slate-400 hover:text-white text-sm">{{ __('Clear') }}</a>
            @endif
            <span class="text-xs text-slate-500 ml-auto self-center">{{ __('Scopes the tables & metrics below') }}</span>
        </form>

        {{-- Usage by model (this month) --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">{{ __('Usage by Model (this month)') }}</h3>
                <a href="{{ route('admin.ai.prices.index') }}" class="text-xs text-blue-400 hover:text-blue-300">{{ __('Manage model pricing →') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Provider') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Model') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Calls') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Tokens') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byModel as $row)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4">{{ $row->provider }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row->model }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((int) $row->calls) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((int) $row->tokens) }}</td>
                                <td class="py-2 text-right">${{ number_format((float) $row->total_cost, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Usage by feature --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ __('Usage by Feature (this month)') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Feature') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Calls') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byFeature as $row)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4">{{ $row->feature ?? __('(unattributed)') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((int) $row->calls) }}</td>
                                <td class="py-2 text-right">${{ number_format((float) $row->total_cost, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Top sessions --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-1">{{ __('Top Sessions by Cost (this month)') }}</h3>
            <p class="text-xs text-slate-500 mb-4">{{ __('A session groups the multiple calls of one flow (e.g. a MOM generation makes ~5 calls).') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Session') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Feature') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Calls') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Tokens') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSessions as $s)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4 font-mono text-xs">{{ \Illuminate\Support\Str::limit($s->session_id, 13, '…') }}</td>
                                <td class="py-2 pr-4">{{ $s->feature ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((int) $s->calls) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((int) $s->tokens) }}</td>
                                <td class="py-2 text-right">${{ number_format((float) $s->total_cost, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-500">{{ __('No multi-call sessions recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent calls --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ __('Recent Calls') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-4 font-medium">{{ __('Time') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Model') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Operation') }}</th>
                            <th class="py-2 pr-4 font-medium text-right">{{ __('Tokens') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLogs as $log)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $log->created_at->format('d M H:i') }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $log->model }}</td>
                                <td class="py-2 pr-4">{{ $log->operation }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($log->total_tokens) }}</td>
                                <td class="py-2 text-right">${{ number_format($log->cost, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
