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
                <p class="text-xs text-slate-500 mt-1">{{ __('Estimated from tracked calls') }}</p>
            </div>
        </div>

        {{-- Health metrics (this month) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <p class="text-sm text-slate-400 mb-1">{{ __('Avg latency') }}</p>
                <p class="text-3xl font-bold text-white">{{ $avgLatency > 0 ? number_format($avgLatency).' ms' : '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ __('Successful calls, this month') }}</p>
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

        {{-- Usage by model (this month) --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ __('Usage by Model (this month)') }}</h3>
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
