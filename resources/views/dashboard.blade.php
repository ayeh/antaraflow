@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Analytics
            </a>
            <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Meeting
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Total Meetings</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_meetings'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Pending Actions</div>
            <div class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['pending_actions'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Overdue Actions</div>
            <div class="text-3xl font-bold text-red-600 mt-1">{{ $stats['overdue_actions'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Meetings This Week</div>
            <div class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['meetings_this_week'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="text-sm font-medium text-gray-500">Completion Rate</div>
            <div class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completion_rate'] }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Meetings</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentMeetings as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $meeting->title }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $meeting->createdBy->name }} &middot; {{ $meeting->created_at->diffForHumans() }}</div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 text-gray-700
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 text-blue-700
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 text-yellow-700
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 text-green-700
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                        </span>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500">No meetings yet.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Your Upcoming Actions</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($upcomingActions as $action)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium text-gray-900">{{ $action->title }}</div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($action->priority->value === 'critical') bg-red-100 text-red-700
                                @elseif($action->priority->value === 'high') bg-orange-100 text-orange-700
                                @elseif($action->priority->value === 'medium') bg-yellow-100 text-yellow-700
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ ucfirst($action->priority->value) }}
                            </span>
                        </div>
                        @if($action->due_date)
                            <div class="text-xs mt-1 {{ $action->due_date->isPast() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                Due: {{ $action->due_date->format('M j, Y') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500">No pending actions.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($upcomingMeetings->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Upcoming Meetings</h2>
        </div>
        <div class="divide-y divide-gray-200">
            @foreach($upcomingMeetings as $meeting)
                <a href="{{ route('meetings.show', $meeting) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $meeting->title }}</div>
                        @if($meeting->meeting_date)
                            <div class="text-xs text-gray-500 mt-1">{{ $meeting->meeting_date->format('M j, Y \a\t g:i A') }}</div>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 text-gray-700
                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 text-blue-700
                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 text-yellow-700
                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 text-green-700
                        @endif">
                        {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
