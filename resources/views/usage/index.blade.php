@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Usage Tracking</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Current period: {{ \Carbon\Carbon::createFromFormat('Y-m', $currentPeriod)->format('F Y') }}</p>
        </div>
        @if(Route::has('subscription.index'))
            <a href="{{ route('subscription.index') }}" class="inline-flex items-center text-sm font-medium text-violet-600 dark:text-violet-400 hover:text-violet-800 dark:hover:text-violet-200 transition-colors">
                Manage Subscription
            </a>
        @else
            <span class="text-sm text-gray-400 dark:text-gray-500">Manage Subscription</span>
        @endif
    </div>

    {{-- Current Subscription --}}
    @if($subscription)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Current Plan</h2>
            <div class="flex items-center gap-4">
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $subscription->subscriptionPlan?->name ?? 'Unknown Plan' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Status:
                        <span class="font-medium @if($subscription->status === 'active') text-emerald-600 dark:text-emerald-400 @else text-amber-600 dark:text-amber-400 @endif">
                            {{ ucfirst($subscription->status) }}
                        </span>
                    </p>
                </div>
                @if($subscription->starts_at)
                    <div class="ml-auto text-right text-sm text-gray-500 dark:text-gray-400">
                        <p>Started: {{ $subscription->starts_at->format('d M Y') }}</p>
                        @if($subscription->ends_at)
                            <p>Ends: {{ $subscription->ends_at->format('d M Y') }}</p>
                        @endif
                        @if($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
                            <p class="text-amber-600 dark:text-amber-400">Trial ends: {{ $subscription->trial_ends_at->format('d M Y') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Current Period Metrics --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">This Month's Usage</h2>

        @if($usage->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No usage data recorded for this period.</p>
        @else
            <div class="space-y-4">
                @foreach($usage as $metric => $entry)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
                                {{ ucwords(str_replace('_', ' ', $metric)) }}
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ number_format((float) $entry->value, 0) }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2">
                            <div class="bg-violet-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                        @if($entry->metadata)
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                @foreach($entry->metadata as $key => $val)
                                    {{ ucwords(str_replace('_', ' ', $key)) }}: {{ $val }}@if(!$loop->last), @endif
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- History Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">12-Month History</h2>
        </div>

        @if($history->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No historical usage data available.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Metric</th>
                            @php
                                $periods = $history->flatten()->pluck('period')->unique()->sortDesc()->values();
                            @endphp
                            @foreach($periods as $period)
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('M Y') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($history as $metric => $entries)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white capitalize">
                                    {{ ucwords(str_replace('_', ' ', $metric)) }}
                                </td>
                                @foreach($periods as $period)
                                    @php
                                        $entry = $entries->firstWhere('period', $period);
                                    @endphp
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-300">
                                        {{ $entry ? number_format((float) $entry->value, 0) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
