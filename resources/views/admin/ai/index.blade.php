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
            </div>
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
                </div>
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ __('Save Settings') }}
                </button>
            </div>
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
