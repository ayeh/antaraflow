@extends('layouts.app')

@section('content')
@php
    $activeFilterCount = collect(['search', 'status', 'project_id', 'date_from', 'date_to'])
        ->filter(fn($k) => request()->filled($k))
        ->count();
    $isCalendar = request('view') === 'calendar';
    $isDense    = false; // stored in localStorage client-side
@endphp

<div class="space-y-6"
     x-data="{
         filterOpen: false,
         dense: JSON.parse(localStorage.getItem('meetings-dense') ?? 'false'),
     }"
     x-init="$watch('dense', v => localStorage.setItem('meetings-dense', v))">

    {{-- Filter Drawer --}}
    @include('meetings.partials._filter-drawer', ['projects' => $projects])

    {{-- ── Page Header ── --}}
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Meetings</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ number_format($stats['total']) }} total</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Filter button --}}
            <button @click="filterOpen = true"
                    class="inline-flex items-center gap-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 px-4 py-2 rounded-xl text-sm font-medium transition-colors relative"
                    aria-label="Open filters">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filters
                @if($activeFilterCount > 0)
                    <span class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-violet-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {{ $activeFilterCount }}
                    </span>
                @endif
            </button>

            {{-- Dense/grid toggle (list view only) --}}
            @if(!$isCalendar)
                <div class="flex rounded-xl border border-gray-200 dark:border-slate-600 overflow-hidden">
                    <button @click="dense = false"
                            :class="!dense ? 'bg-violet-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600'"
                            class="p-2 transition-colors" aria-label="Card grid view" :aria-pressed="!dense">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </button>
                    <button @click="dense = true"
                            :class="dense ? 'bg-violet-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600'"
                            class="p-2 border-l border-gray-200 dark:border-slate-600 transition-colors" aria-label="Table view" :aria-pressed="dense">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            @endif

            {{-- View toggle: List / Calendar --}}
            <div class="flex rounded-xl border border-gray-200 dark:border-slate-600 overflow-hidden text-sm font-medium">
                <a href="{{ route('meetings.index', array_merge(request()->except(['view', 'page']), ['view' => 'list'])) }}"
                   class="px-4 py-2 transition-colors {{ !$isCalendar ? 'bg-violet-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600' }}"
                   aria-pressed="{{ !$isCalendar ? 'true' : 'false' }}">
                    List
                </a>
                <a href="{{ route('meetings.index', array_merge(request()->except(['view', 'page']), ['view' => 'calendar'])) }}"
                   class="px-4 py-2 border-l border-gray-200 dark:border-slate-600 transition-colors {{ $isCalendar ? 'bg-violet-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-600' }}"
                   aria-pressed="{{ $isCalendar ? 'true' : 'false' }}">
                    Calendar
                </a>
            </div>

            {{-- New MOM button --}}
            @can('create', \App\Domain\Meeting\Models\MinutesOfMeeting::class)
                <a href="{{ route('meetings.create') }}"
                   class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New MOM
                </a>
            @endcan
        </div>
    </div>

    @if(!$isCalendar)
        {{-- ── Stat Cards ── --}}
        @include('meetings.partials._stat-cards', ['stats' => $stats])

        {{-- ── Card Grid (default) / Table (dense) ── --}}
        <div>
            {{-- Card Grid --}}
            <div x-show="!dense" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($meetings as $meeting)
                    @include('meetings.partials._meeting-card', ['meeting' => $meeting])
                @empty
                    <div class="col-span-full">
                        @include('meetings.partials._empty-state')
                    </div>
                @endforelse
            </div>

            {{-- Dense Table --}}
            <div x-show="dense" x-cloak class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" role="grid">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MOM No.</th>
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Project</th>
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Date</th>
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Items</th>
                                <th scope="col" class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                            @forelse($meetings as $meeting)
                                @php
                                    $total = $meeting->actionItems->count();
                                    $done  = $meeting->actionItems->where('status', \App\Support\Enums\ActionItemStatus::Completed)->count();
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4 font-mono text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $meeting->mom_number ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('meetings.show', $meeting) }}" class="font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 transition-colors">{{ $meeting->title }}</a>
                                        @if($meeting->createdBy)
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $meeting->createdBy->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 hidden md:table-cell text-gray-500 dark:text-gray-400">
                                        {{ $meeting->project?->name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 hidden sm:table-cell text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ $meeting->meeting_date?->format('j M Y') ?? 'Not set' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @include('meetings.partials._status-badge', ['status' => $meeting->status])
                                            @if($meeting->share_with_client)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Shared</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 hidden lg:table-cell">
                                        @if($total > 0)
                                            <span class="text-sm {{ $done === $total ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-300' }} font-medium">{{ $done }}/{{ $total }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:underline">View</a>
                                            @can('update', $meeting)
                                                @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft)
                                                    <a href="{{ route('meetings.edit', $meeting) }}" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:underline">Edit</a>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        @include('meetings.partials._empty-state')
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Pagination --}}
        @if($meetings->hasPages())
            <div class="pt-2">
                {{ $meetings->withQueryString()->links() }}
            </div>
        @endif

    @else
        {{-- ── Calendar View ── --}}
        @include('meetings.partials.calendar-view')
    @endif

</div>
@endsection
