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
    @php
        $dailyPct  = $dailyBudget > 0 ? min(100, ($todaySpend  / $dailyBudget) * 100) : 0;
        $monthPct  = $hardCap     > 0 ? min(100, ($monthSpend  / $hardCap)     * 100) : 0;
        $budgetBar = fn (float $pct) => $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-blue-500');
        $healthStatus = $errorRate > 0.1 ? 'critical' : ($errorRate > 0.05 ? 'degraded' : 'healthy');
        $healthColor  = match ($healthStatus) { 'healthy' => 'bg-green-400', 'degraded' => 'bg-amber-400', default => 'bg-red-400' };
        $healthText   = match ($healthStatus) { 'healthy' => 'text-green-400', 'degraded' => 'text-amber-400', default => 'text-red-400' };
    @endphp

    <div class="max-w-6xl space-y-6">

        {{-- ═══ 1. CONTROL STRIP ═══════════════════════════════════════════════ --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl px-5 py-4">
            <div class="flex flex-wrap items-center gap-4 justify-between">
                <div class="flex items-center gap-5">
                    {{-- AI status + toggle badge --}}
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold
                        {{ $enabled ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30' }}">
                        <span class="size-2 rounded-full {{ $enabled ? 'bg-green-400 animate-pulse' : 'bg-red-400' }}"></span>
                        {{ $enabled ? __('AI LIVE') : __('AI OFF') }}
                    </span>

                    {{-- Active model --}}
                    <div class="hidden sm:block">
                        <p class="text-xs text-slate-400">{{ __('Active provider / model') }}</p>
                        <p class="text-sm font-medium text-white font-mono">{{ $activeProvider }} · {{ $activeModel }}</p>
                    </div>

                    {{-- Health pill --}}
                    <div class="hidden md:flex items-center gap-2 text-xs">
                        <span class="size-2 rounded-full {{ $healthColor }}"></span>
                        <span class="text-slate-400">{{ __('Health:') }}
                            <span class="{{ $healthText }} capitalize">{{ $healthStatus }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.ai.org-budgets.index') }}"
                       class="px-3 py-1.5 text-xs text-slate-300 hover:text-white border border-slate-600 hover:border-slate-500 rounded-lg transition-colors">
                        {{ __('Org budgets') }}
                    </a>
                    <a href="{{ route('admin.ai.prices.index') }}"
                       class="px-3 py-1.5 text-xs text-slate-300 hover:text-white border border-slate-600 hover:border-slate-500 rounded-lg transition-colors">
                        {{ __('Model pricing') }}
                    </a>
                    <form method="POST" action="{{ route('admin.ai.toggle') }}" class="ml-2">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
                        <button type="submit"
                                class="px-5 py-2 text-sm font-medium rounded-lg transition-colors text-white
                                    {{ $enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }}">
                            {{ $enabled ? __('Turn OFF') : __('Turn ON') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══ 2. SPEND KPIs ═══════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Today spend --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">{{ __('Today') }}</p>
                <p class="text-2xl font-bold text-white">${{ number_format($todaySpend, 2) }}</p>
                @if($dailyBudget > 0)
                    <div class="mt-2.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-1 rounded-full {{ $budgetBar($dailyPct) }}" style="width:{{ $dailyPct }}%"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ number_format($dailyPct, 0) }}% of ${{ number_format($dailyBudget, 0) }} {{ __('daily budget') }}</p>
                @else
                    <p class="text-xs text-slate-500 mt-2">{{ __('No daily budget set') }}</p>
                @endif
            </div>

            {{-- Month spend --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">{{ __('This month') }}</p>
                <p class="text-2xl font-bold text-white">${{ number_format($monthSpend, 2) }}</p>
                @if($hardCap > 0)
                    <div class="mt-2.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-1 rounded-full {{ $budgetBar($monthPct) }}" style="width:{{ $monthPct }}%"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ number_format($monthPct, 0) }}% of ${{ number_format($hardCap, 0) }} {{ __('hard cap') }}</p>
                @else
                    <p class="text-xs text-slate-500 mt-2">{{ __('No hard cap set') }}</p>
                @endif
            </div>

            {{-- Error rate --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">{{ __('Error rate') }}</p>
                <p class="text-2xl font-bold {{ $errorRate > 0.05 ? 'text-red-400' : 'text-white' }}">
                    {{ number_format($errorRate * 100, 1) }}%
                </p>
                <div class="mt-2 grid grid-cols-3 text-center divide-x divide-slate-700">
                    <div>
                        <p class="text-xs font-mono text-white">{{ number_format($latency['p50']) }}</p>
                        <p class="text-[10px] text-slate-500">p50</p>
                    </div>
                    <div>
                        <p class="text-xs font-mono text-white">{{ number_format($latency['p95']) }}</p>
                        <p class="text-[10px] text-slate-500">p95</p>
                    </div>
                    <div>
                        <p class="text-xs font-mono text-white">{{ number_format($latency['p99']) }}</p>
                        <p class="text-[10px] text-slate-500">p99</p>
                    </div>
                </div>
            </div>

            {{-- Cache hit --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">{{ __('Cache hit') }}</p>
                <p class="text-2xl font-bold {{ $cacheHitRate > 0.3 ? 'text-green-400' : 'text-white' }}">
                    {{ number_format($cacheHitRate * 100, 1) }}%
                </p>
                <p class="text-xs text-slate-500 mt-2">{{ __('Cached prompt tokens, this month') }}</p>
            </div>
        </div>

        {{-- ═══ 3. DAILY SPEND CHART ════════════════════════════════════════════ --}}
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
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-white">{{ __('Daily Spend — last 30 days') }}</h3>
                <div class="flex items-center gap-4 text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block w-3 h-2 rounded-sm bg-blue-500"></span>{{ __('Normal') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block w-3 h-2 rounded-sm bg-red-500"></span>{{ __('Anomaly') }}
                    </span>
                    @if($dailyBaseline > 0)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block w-3 border-t border-dashed border-amber-400"></span>{{ __('Threshold') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 {{ $chartW }} {{ $chartH + 22 }}" class="w-full" style="min-width:480px" role="img"
                     aria-label="{{ __('Daily AI spend over the last 30 days') }}">
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
            <p class="text-xs text-slate-500 mt-2">{{ __('Bars turn red when a day meets the anomaly threshold (baseline × multiplier). Hover for exact amount.') }}</p>
        </div>

        {{-- ═══ 4. PROVIDER RECONCILIATION ══════════════════════════════════════ --}}
        <div>
            <div class="flex items-center gap-3 mb-3">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest whitespace-nowrap">{{ __('Provider Reconciliation') }}</p>
                <div class="flex-1 border-t border-slate-700"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- OpenAI --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-sm font-medium text-white">OpenAI</p>
                        <a href="https://platform.openai.com/usage" target="_blank" rel="noopener"
                           class="text-[10px] text-blue-400 hover:text-blue-300">{{ __('Dashboard ↗') }}</a>
                    </div>
                    @if(! $openAiConfigured)
                        <p class="text-xs text-slate-400">{{ __('Set OPENAI_ADMIN_KEY (sk-admin-…) to pull live spend.') }}</p>
                    @else
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-slate-400 mb-0.5">
                                    {{ __('Actual this month') }}
                                    <span class="ml-1 text-[10px] {{ $openAiProjectScoped ? 'text-green-400' : 'text-amber-400' }}">
                                        · {{ $openAiProjectScoped ? __('this project') : __('whole org') }}
                                    </span>
                                </p>
                                <p class="text-xl font-bold text-white">{{ $openAiMonthCost === null ? '—' : '$'.number_format($openAiMonthCost, 2) }}</p>
                                @unless($openAiProjectScoped)
                                    <p class="text-[10px] text-amber-400/80 mt-0.5">{{ __('Set OPENAI_PROJECT_ID to scope.') }}</p>
                                @endunless
                            </div>
                            @if($estimatedBalance !== null)
                                <div>
                                    <p class="text-xs text-slate-400 mb-0.5">{{ __('Est. balance') }}</p>
                                    <p class="text-lg font-bold {{ $estimatedBalance <= 0 ? 'text-red-400' : 'text-green-400' }}">
                                        ${{ number_format($estimatedBalance, 2) }}
                                    </p>
                                    <p class="text-[10px] text-slate-500 mt-0.5">
                                        {{ __('Top-up $:a on :d', ['a' => number_format($creditTopup, 0), 'd' => $creditTopupDate]) }}
                                    </p>
                                </div>
                            @else
                                <p class="text-xs text-slate-500">{{ __('Enter top-up details in Configuration to estimate balance.') }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Anthropic --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <p class="text-sm font-medium text-white mb-3">Anthropic (Claude)</p>
                    @if(! $anthropicConfigured)
                        <p class="text-xs text-slate-400">{{ __('Set ANTHROPIC_ADMIN_KEY (sk-ant-admin-…) to pull live spend.') }}</p>
                    @else
                        <p class="text-xs text-slate-400 mb-0.5">{{ __('Actual this month') }}</p>
                        <p class="text-xl font-bold text-white">{{ $anthropicMonthCost === null ? '—' : '$'.number_format($anthropicMonthCost, 2) }}</p>
                        <p class="text-[10px] text-slate-500 mt-1">{{ __('From the Cost Report API') }}</p>
                    @endif
                </div>

                {{-- Google --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-sm font-medium text-white">Google (Gemini)</p>
                        <a href="https://console.cloud.google.com/billing" target="_blank" rel="noopener"
                           class="text-[10px] text-blue-400 hover:text-blue-300">{{ __('GCP ↗') }}</a>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('No simple costs API. Actuals live in GCP Billing (BigQuery export).') }}</p>
                </div>
            </div>
            <p class="text-[10px] text-slate-600 mt-2 text-right">{{ __('OpenAI shows no true balance endpoint — figure is estimated from last top-up.') }}</p>
        </div>

        {{-- ═══ 5. ANALYTICS ════════════════════════════════════════════════════ --}}
        <div>
            <div class="flex items-center gap-3 mb-3">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest whitespace-nowrap">{{ __('Analytics — this month') }}</p>
                <div class="flex-1 border-t border-slate-700"></div>
                <form method="GET" action="{{ route('admin.ai.index') }}" class="flex items-center gap-2 shrink-0">
                    <select name="provider" class="bg-slate-700 border border-slate-600 text-white rounded-lg px-2 py-1 text-xs">
                        <option value="">{{ __('All providers') }}</option>
                        @foreach($providerOptions as $p)<option value="{{ $p }}" @selected($selectedProvider === $p)>{{ $p }}</option>@endforeach
                    </select>
                    <select name="feature" class="bg-slate-700 border border-slate-600 text-white rounded-lg px-2 py-1 text-xs">
                        <option value="">{{ __('All features') }}</option>
                        @foreach($featureOptions as $f)<option value="{{ $f }}" @selected($selectedFeature === $f)>{{ $f }}</option>@endforeach
                    </select>
                    <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg">{{ __('Filter') }}</button>
                    @if($selectedProvider || $selectedFeature)
                        <a href="{{ route('admin.ai.index') }}" class="text-xs text-slate-400 hover:text-white">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            {{-- By model + By feature, side by side --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                {{-- Usage by model --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-white mb-3">{{ __('Usage by Model') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-400 border-b border-slate-700">
                                    <th class="py-1.5 pr-3 font-medium">{{ __('Provider / Model') }}</th>
                                    <th class="py-1.5 pr-3 font-medium text-right">{{ __('Calls') }}</th>
                                    <th class="py-1.5 pr-3 font-medium text-right">{{ __('Tokens') }}</th>
                                    <th class="py-1.5 font-medium text-right">{{ __('Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($byModel as $row)
                                    <tr class="border-b border-slate-700/40 text-slate-200">
                                        <td class="py-1.5 pr-3">
                                            <span class="text-xs text-slate-400">{{ $row->provider }}</span>
                                            <span class="block font-mono text-xs">{{ $row->model }}</span>
                                        </td>
                                        <td class="py-1.5 pr-3 text-right text-xs">{{ number_format((int) $row->calls) }}</td>
                                        <td class="py-1.5 pr-3 text-right text-xs">{{ number_format((int) $row->tokens) }}</td>
                                        <td class="py-1.5 text-right text-xs">${{ number_format((float) $row->total_cost, 4) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-xs text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Usage by feature --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-white mb-3">{{ __('Usage by Feature') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-400 border-b border-slate-700">
                                    <th class="py-1.5 pr-3 font-medium">{{ __('Feature') }}</th>
                                    <th class="py-1.5 pr-3 font-medium text-right">{{ __('Calls') }}</th>
                                    <th class="py-1.5 font-medium text-right">{{ __('Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($byFeature as $row)
                                    <tr class="border-b border-slate-700/40 text-slate-200">
                                        <td class="py-1.5 pr-3 text-xs">{{ $row->feature ?? __('(unattributed)') }}</td>
                                        <td class="py-1.5 pr-3 text-right text-xs">{{ number_format((int) $row->calls) }}</td>
                                        <td class="py-1.5 text-right text-xs">${{ number_format((float) $row->total_cost, 4) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center text-xs text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Top sessions --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 mb-4">
                <h3 class="text-sm font-semibold text-white mb-0.5">{{ __('Top Sessions by Cost') }}</h3>
                <p class="text-xs text-slate-500 mb-3">{{ __('One session groups multiple calls for a single flow (e.g. MOM generation ≈ 5 calls).') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-400 border-b border-slate-700">
                                <th class="py-1.5 pr-3 font-medium">{{ __('Session') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('Feature') }}</th>
                                <th class="py-1.5 pr-3 font-medium text-right">{{ __('Calls') }}</th>
                                <th class="py-1.5 pr-3 font-medium text-right">{{ __('Tokens') }}</th>
                                <th class="py-1.5 font-medium text-right">{{ __('Cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topSessions as $s)
                                <tr class="border-b border-slate-700/40 text-slate-200">
                                    <td class="py-1.5 pr-3 font-mono text-xs">{{ \Illuminate\Support\Str::limit($s->session_id, 13, '…') }}</td>
                                    <td class="py-1.5 pr-3 text-xs">{{ $s->feature ?? '—' }}</td>
                                    <td class="py-1.5 pr-3 text-right text-xs">{{ number_format((int) $s->calls) }}</td>
                                    <td class="py-1.5 pr-3 text-right text-xs">{{ number_format((int) $s->tokens) }}</td>
                                    <td class="py-1.5 text-right text-xs">${{ number_format((float) $s->total_cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-xs text-slate-500">{{ __('No multi-call sessions recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent calls with status colour --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-white mb-3">{{ __('Recent Calls') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-400 border-b border-slate-700">
                                <th class="py-1.5 pr-3 font-medium w-4"></th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('Time') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('Model') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('Feature / Operation') }}</th>
                                <th class="py-1.5 pr-3 font-medium text-right">{{ __('Tokens') }}</th>
                                <th class="py-1.5 font-medium text-right">{{ __('Cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                                @php $isError = ($log->status ?? 'success') === 'error'; @endphp
                                <tr class="border-b border-slate-700/40 {{ $isError ? 'text-red-300' : 'text-slate-200' }}">
                                    <td class="py-1.5 pr-3">
                                        <span class="size-1.5 rounded-full inline-block {{ $isError ? 'bg-red-400' : 'bg-green-400' }}"></span>
                                    </td>
                                    <td class="py-1.5 pr-3 whitespace-nowrap text-xs">{{ $log->created_at->format('d M H:i') }}</td>
                                    <td class="py-1.5 pr-3 font-mono text-xs">{{ $log->model }}</td>
                                    <td class="py-1.5 pr-3 text-xs">
                                        @if($log->feature ?? null)<span class="text-slate-400">{{ $log->feature }} · </span>@endif{{ $log->operation }}
                                    </td>
                                    <td class="py-1.5 pr-3 text-right text-xs">{{ number_format($log->total_tokens) }}</td>
                                    <td class="py-1.5 text-right text-xs">${{ number_format($log->cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-xs text-slate-500">{{ __('No usage recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═══ 6. CONFIGURATION ════════════════════════════════════════════════ --}}
        <div>
            <div class="flex items-center gap-3 mb-3">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest whitespace-nowrap">{{ __('Configuration') }}</p>
                <div class="flex-1 border-t border-slate-700"></div>
            </div>

            {{-- Budget & alerts --}}
            <form method="POST" action="{{ route('admin.ai.update-settings') }}">
                @csrf
                @method('PUT')
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 space-y-6">
                    <div>
                        <h3 class="text-sm font-semibold text-white mb-1">{{ __('Budget & Alerts') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('Checked hourly. Set 0 to disable a threshold. The hard cap auto-disables AI when exceeded.') }}</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="daily_budget" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Daily budget — alert (USD)') }}</label>
                            <input type="number" step="0.01" min="0" name="daily_budget" id="daily_budget"
                                   value="{{ old('daily_budget', $dailyBudget) }}"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('daily_budget') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="hard_cap" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Hard cap — auto-disable (USD)') }}</label>
                            <input type="number" step="0.01" min="0" name="hard_cap" id="hard_cap"
                                   value="{{ old('hard_cap', $hardCap) }}"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('hard_cap') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="alert_email" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Alert email') }}</label>
                            <input type="email" name="alert_email" id="alert_email"
                                   value="{{ old('alert_email', $alertEmail) }}" placeholder="ops@example.com"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('alert_email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="alert_telegram_chat_id" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Alert Telegram chat ID') }}</label>
                            <input type="text" name="alert_telegram_chat_id" id="alert_telegram_chat_id"
                                   value="{{ old('alert_telegram_chat_id', $alertTelegram) }}" placeholder="-1001234567890"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('alert_telegram_chat_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-[10px] text-slate-500">{{ __('Requires TELEGRAM_BOT_TOKEN in the environment.') }}</p>
                        </div>
                        <div>
                            <label for="credit_topup" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Last top-up amount (USD)') }}</label>
                            <input type="number" step="0.01" min="0" name="credit_topup" id="credit_topup"
                                   value="{{ old('credit_topup', $creditTopup) }}" placeholder="100.00"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('credit_topup') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-[10px] text-slate-500">{{ __('Used to estimate remaining balance vs OpenAI actual spend.') }}</p>
                        </div>
                        <div>
                            <label for="credit_topup_date" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Top-up date') }}</label>
                            <input type="date" name="credit_topup_date" id="credit_topup_date"
                                   value="{{ old('credit_topup_date', $creditTopupDate) }}"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('credit_topup_date') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-slate-700 pt-5 space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="anomaly_enabled" value="0">
                            <input type="checkbox" name="anomaly_enabled" value="1" @checked($anomalyEnabled)
                                   class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500">
                            <span class="text-xs text-slate-300">{{ __('Enable spend anomaly detection (rolling 7-day baseline)') }}</span>
                        </label>
                        <div class="max-w-xs">
                            <label for="anomaly_multiplier" class="block text-xs font-medium text-slate-300 mb-1">{{ __('Anomaly multiplier (× baseline)') }}</label>
                            <input type="number" step="0.1" min="1" max="100" name="anomaly_multiplier" id="anomaly_multiplier"
                                   value="{{ old('anomaly_multiplier', $anomalyMultiplier) }}"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('anomaly_multiplier') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-[10px] text-slate-500">{{ __('Alert when today\'s spend ≥ this many times the 7-day daily average.') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-700 pt-5">
                        <button type="submit"
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            {{ __('Save Settings') }}
                        </button>
                        <button type="submit" form="ai-test-alert-form"
                                class="px-5 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition-colors">
                            {{ __('Send Test Alert') }}
                        </button>
                        <p class="text-[10px] text-slate-500">{{ __('Save first if you just changed email or Telegram.') }}</p>
                    </div>
                </div>
            </form>

            <form id="ai-test-alert-form" method="POST" action="{{ route('admin.ai.test-alert') }}" class="hidden">
                @csrf
            </form>

            {{-- Provider, Model & API Keys (editable) --}}
            <form method="POST" action="{{ route('admin.ai.update-provider') }}" class="mt-4">
                @csrf
                @method('PUT')
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 space-y-5">
                    <div class="flex items-start justify-between flex-wrap gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-white mb-1">{{ __('Provider, Model & API Keys') }}</h3>
                            <p class="text-xs text-slate-400">{{ __('Switch the active provider, override models, and set API keys. Overrides here take priority over the server .env.') }}</p>
                        </div>
                        <span class="text-xs text-slate-400 shrink-0">
                            {{ __('Active:') }} <span class="text-slate-200 font-medium">{{ $activeProvider }}</span>
                            @if($activeModel)<span class="font-mono text-xs ml-1">· {{ $activeModel }}</span>@endif
                        </span>
                    </div>

                    {{-- Active provider selector --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-2">{{ __('Active provider') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($managedProviders as $p)
                                <label class="cursor-pointer">
                                    <input type="radio" name="active_provider" value="{{ $p['name'] }}"
                                           class="peer sr-only" @checked($activeProvider === $p['name'])>
                                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm capitalize transition-colors
                                        border-slate-600 text-slate-300 hover:border-slate-500
                                        peer-checked:border-blue-500 peer-checked:bg-blue-500/10 peer-checked:text-white">
                                        {{ $p['name'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('active_provider') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Per-provider model + key --}}
                    <div class="space-y-4 border-t border-slate-700 pt-5">
                        @foreach($managedProviders as $p)
                            <div class="grid grid-cols-1 md:grid-cols-[7rem_1fr_1fr] gap-3 md:items-start">
                                <div class="flex items-center gap-2 md:pt-2">
                                    <span class="text-sm font-medium text-white capitalize">{{ $p['name'] }}</span>
                                    @if($activeProvider === $p['name'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-500/15 text-green-400">{{ __('ACTIVE') }}</span>
                                    @endif
                                </div>

                                <div>
                                    <label class="block text-[10px] text-slate-500 mb-1">{{ __('Model') }}</label>
                                    <input type="text" name="models[{{ $p['name'] }}]" value="{{ old('models.'.$p['name'], $p['model']) }}"
                                           placeholder="{{ __('e.g. gpt-5.4-mini') }}"
                                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">
                                    @error('models.'.$p['name']) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-[10px] text-slate-500 mb-1">
                                        {{ __('API key') }}
                                        @if($p['keyMasked'])
                                            <span class="font-mono text-slate-400">· {{ $p['keyMasked'] }}</span>
                                            <span class="ml-1 {{ $p['keyFromDb'] ? 'text-blue-400' : 'text-slate-500' }}">({{ $p['keyFromDb'] ? __('from DB') : __('from .env') }})</span>
                                        @else
                                            <span class="text-slate-500">· {{ __('not set') }}</span>
                                        @endif
                                    </label>
                                    <input type="password" name="keys[{{ $p['name'] }}]" autocomplete="new-password"
                                           placeholder="{{ $p['keyMasked'] ? __('Leave blank to keep current') : __('Paste key to set') }}"
                                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">
                                    @error('keys.'.$p['name']) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                                    @if($p['keyFromDb'])
                                        <label class="flex items-center gap-2 mt-1.5 cursor-pointer">
                                            <input type="checkbox" name="clear_keys[{{ $p['name'] }}]" value="1"
                                                   class="w-3.5 h-3.5 rounded border-slate-600 bg-slate-700 text-red-500 focus:ring-red-500">
                                            <span class="text-[10px] text-slate-400">{{ __('Remove DB key (revert to .env)') }}</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-700 pt-5">
                        <button type="submit"
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            {{ __('Save Provider Settings') }}
                        </button>
                        <p class="text-[10px] text-slate-500">{{ __('Keys are encrypted at rest. Blank key field = keep current. Local providers (Ollama, Whisper) stay .env-only.') }}</p>
                    </div>
                </div>
            </form>

            {{-- Other keys in use (read-only reference) --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 mt-4">
                <h3 class="text-sm font-semibold text-white mb-1">{{ __('Other Keys in Use') }}</h3>
                <p class="text-[10px] text-slate-500 mb-4">{{ __('Admin/billing & service keys — managed in the server .env, masked here for reference.') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-400 border-b border-slate-700">
                                <th class="py-1.5 pr-4 font-medium">{{ __('Purpose') }}</th>
                                <th class="py-1.5 pr-4 font-medium">{{ __('Env var') }}</th>
                                <th class="py-1.5 pr-4 font-medium">{{ __('Key') }}</th>
                                <th class="py-1.5 font-medium">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($apiKeys as $key)
                                <tr class="border-b border-slate-700/40 text-slate-200">
                                    <td class="py-1.5 pr-4 text-xs">{{ $key['label'] }}</td>
                                    <td class="py-1.5 pr-4 font-mono text-[10px] text-slate-400">{{ $key['env'] }}</td>
                                    <td class="py-1.5 pr-4 font-mono text-[10px]">{{ $key['masked'] ?? '—' }}</td>
                                    <td class="py-1.5">
                                        @if($key['masked'])
                                            <span class="inline-flex items-center gap-1.5 text-green-400 text-xs">
                                                <span class="size-1.5 rounded-full bg-green-400"></span>{{ __('Set') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-slate-500 text-xs">
                                                <span class="size-1.5 rounded-full bg-slate-600"></span>{{ __('Not set') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
