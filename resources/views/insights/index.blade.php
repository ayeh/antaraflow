@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ typeFilter: '' }">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Insights</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Proactive insights from MemoAdvisor</p>
        </div>
    </div>

    {{-- Filter by type --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <label for="type_filter" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Filter by Type</label>
                <select id="type_filter" x-model="typeFilter"
                    class="block rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white text-sm px-3 py-1.5 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <option value="">All Types</option>
                    <option value="recurring_topic">Recurring Topic</option>
                    <option value="decision_no_followup">Decision No Follow-up</option>
                    <option value="reraised_action_item">Reraised Action Item</option>
                    <option value="overdue_pattern">Overdue Pattern</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Insights list --}}
    <div class="space-y-3">
        @forelse($insights as $insight)
            <div
                x-show="typeFilter === '' || typeFilter === '{{ $insight->type }}'"
                class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 {{ $insight->is_read ? 'opacity-75' : '' }}"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            {{-- Severity badge --}}
                            @if($insight->severity === 'critical')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">Critical</span>
                            @elseif($insight->severity === 'warning')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">Warning</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">Info</span>
                            @endif

                            {{-- Type label --}}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">
                                {{ str_replace('_', ' ', ucfirst($insight->type)) }}
                            </span>

                            @if(!$insight->is_read)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">New</span>
                            @endif
                        </div>

                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $insight->title }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $insight->description }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ $insight->generated_at->diffForHumans() }}</p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if(!$insight->is_read)
                            <form method="POST" action="{{ route('insights.read', $insight) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Mark Read
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('insights.dismiss', $insight) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Dismiss
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No insights yet</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">MemoAdvisor will generate insights as more meeting data is available.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($insights->hasPages())
        <div class="mt-4">
            {{ $insights->links() }}
        </div>
    @endif
</div>
@endsection
