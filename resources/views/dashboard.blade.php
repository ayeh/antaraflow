@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Analytics
            </a>
            @if($canCreateMeeting)
            <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Meeting
            </a>
            @endif
        </div>
    </div>

    {{-- Stat cards — full rewrite coming in Task 3 --}}

    {{-- Content panels — full rewrite coming in Task 3 --}}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your Upcoming Actions</h2>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($upcomingActions as $action)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $action->title }}</div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($action->priority->value === 'critical') bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300
                                @elseif($action->priority->value === 'high') bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300
                                @elseif($action->priority->value === 'medium') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300
                                @else bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300
                                @endif">
                                {{ ucfirst($action->priority->value) }}
                            </span>
                        </div>
                        @if($action->due_date)
                            <div class="text-xs mt-1 {{ $action->due_date->isPast() ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                Due: {{ $action->due_date->format('M j, Y') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No pending actions.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">This Week's Meetings</h2>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($thisWeekMeetings as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->title }}</div>
                            @if($meeting->meeting_date)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $meeting->meeting_date->format('M j, Y') }}</div>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No meetings this week.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
