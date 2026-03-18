@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ now()->format('l, j F Y') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}"
               class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Analytics
            </a>
            @if($canCreateMeeting)
                <a href="{{ route('meetings.create') }}"
                   class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New MOM
                </a>
            @endif
        </div>
    </div>

    {{-- Stat Cards --}}
    @php
    $statCards = [
        [
            'label'  => 'My Actions',
            'count'  => $stats['my_actions'],
            'href'   => route('action-items.dashboard'),
            'color'  => 'violet',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        ],
        [
            'label'  => 'Overdue',
            'count'  => $stats['my_overdue'],
            'href'   => route('action-items.dashboard'),
            'color'  => 'red',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        [
            'label'  => 'This Week',
            'count'  => $stats['meetings_this_week'],
            'href'   => route('meetings.index', ['view' => 'calendar']),
            'color'  => 'blue',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        ],
        [
            'label'  => 'Pending Approval',
            'count'  => $stats['pending_approval'],
            'href'   => route('meetings.index', ['status' => 'finalized']),
            'color'  => 'amber',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        [
            'label'  => 'Completion Rate',
            'count'  => $stats['my_completion_rate'] . '%',
            'href'   => route('action-items.dashboard'),
            'color'  => 'green',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        ],
    ];
    $colorMap = [
        'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900/30', 'icon' => 'text-violet-600 dark:text-violet-400', 'hover' => 'hover:border-violet-300 dark:hover:border-violet-700'],
        'red'    => ['bg' => 'bg-red-100 dark:bg-red-900/30',       'icon' => 'text-red-600 dark:text-red-400',       'hover' => 'hover:border-red-300 dark:hover:border-red-700'],
        'blue'   => ['bg' => 'bg-blue-100 dark:bg-blue-900/30',     'icon' => 'text-blue-600 dark:text-blue-400',     'hover' => 'hover:border-blue-300 dark:hover:border-blue-700'],
        'amber'  => ['bg' => 'bg-amber-100 dark:bg-amber-900/30',   'icon' => 'text-amber-600 dark:text-amber-400',   'hover' => 'hover:border-amber-300 dark:hover:border-amber-700'],
        'green'  => ['bg' => 'bg-green-100 dark:bg-green-900/30',   'icon' => 'text-green-600 dark:text-green-400',   'hover' => 'hover:border-green-300 dark:hover:border-green-700'],
    ];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach($statCards as $card)
            @php $c = $colorMap[$card['color']]; @endphp
            <a href="{{ $card['href'] }}"
               class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 transition-all hover:shadow-md {{ $c['hover'] }}"
               aria-label="{{ $card['label'] }}: {{ $card['count'] }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-9 h-9 rounded-xl {{ $c['bg'] }} flex items-center justify-center">
                        <svg class="w-5 h-5 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $card['icon'] !!}
                        </svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $card['count'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $card['label'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- Needs Attention Banner --}}
    @if($myOverdueCount > 0 || $pendingApproval > 0)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 space-y-2">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Needs Attention</span>
            </div>
            @if($myOverdueCount > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                        {{ $myOverdueCount }} action {{ Str::plural('item', $myOverdueCount) }} overdue
                    </span>
                    <a href="{{ route('action-items.dashboard') }}" class="text-amber-700 dark:text-amber-400 font-medium hover:underline text-xs">View all →</a>
                </div>
            @endif
            @if($pendingApproval > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                        {{ $pendingApproval }} {{ Str::plural('MOM', $pendingApproval) }} pending approval
                    </span>
                    <a href="{{ route('meetings.index', ['status' => 'finalized']) }}" class="text-amber-700 dark:text-amber-400 font-medium hover:underline text-xs">Review now →</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Main content — added in Tasks 4 & 5 --}}

</div>
@endsection
