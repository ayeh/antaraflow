@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ statusFilter: '{{ request('status') }}' }">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Meetings</h1>
        <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            + New MOM
        </a>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <button type="button"
            @click="statusFilter = ''; $refs.statusSelect.value = ''; $refs.filterForm.submit()"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-left transition-all hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 {{ !request('status') ? 'ring-2 ring-gray-900 dark:ring-white' : '' }}">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</div>
        </button>

        <button type="button"
            @click="statusFilter = 'draft'; $refs.statusSelect.value = 'draft'; $refs.filterForm.submit()"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-left transition-all hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 {{ request('status') === 'draft' ? 'ring-2 ring-blue-500' : '' }}">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Draft</div>
            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $stats['draft'] }}</div>
        </button>

        <button type="button"
            @click="statusFilter = 'finalized'; $refs.statusSelect.value = 'finalized'; $refs.filterForm.submit()"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-left transition-all hover:shadow-md hover:border-orange-300 dark:hover:border-orange-600 {{ request('status') === 'finalized' ? 'ring-2 ring-orange-500' : '' }}">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Finalized</div>
            <div class="text-3xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ $stats['finalized'] }}</div>
        </button>

        <button type="button"
            @click="statusFilter = 'approved'; $refs.statusSelect.value = 'approved'; $refs.filterForm.submit()"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-left transition-all hover:shadow-md hover:border-green-300 dark:hover:border-green-600 {{ request('status') === 'approved' ? 'ring-2 ring-green-500' : '' }}">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</div>
            <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $stats['approved'] }}</div>
        </button>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4">
            <form x-ref="filterForm" method="GET" action="{{ route('meetings.index') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title or MOM number..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
                <select name="project_id" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <option value="">All Projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }} ({{ $project->code }})</option>
                    @endforeach
                </select>
                <select x-ref="statusSelect" name="status" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <option value="">All Statuses</option>
                    @foreach(\App\Support\Enums\MeetingStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Search
                </button>
                @if(request()->hasAny(['search', 'status', 'project_id']))
                    <a href="{{ route('meetings.index') }}" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        {{-- Data Table --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-y border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">MOM No.</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meeting Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meeting Date</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action Items</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($meetings as $meeting)
                        @php
                            $totalActions = $meeting->actionItems->count();
                            $completedActions = $meeting->actionItems->where('status', \App\Support\Enums\ActionItemStatus::Completed)->count();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ $meeting->mom_number ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $meeting) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $meeting->title }}</a>
                                @if($meeting->createdBy)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">by {{ $meeting->createdBy->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($meeting->project)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $meeting->project->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $meeting->project->code }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $meeting->meeting_date ? $meeting->meeting_date->format('M j, Y') : 'Not set' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300
                                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300
                                        @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                                    </span>
                                    @if($meeting->share_with_client)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                            Shared
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($totalActions > 0)
                                    <span class="text-sm font-medium {{ $completedActions === $totalActions ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $completedActions }}/{{ $totalActions }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 font-medium">View</a>
                                    @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft)
                                        <a href="{{ route('meetings.edit', $meeting) }}" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 font-medium">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No meetings found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first meeting.</p>
                                <div class="mt-6">
                                    <a href="{{ route('meetings.create') }}" class="inline-flex items-center rounded-md bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-500">
                                        + New MOM
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($meetings->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $meetings->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
