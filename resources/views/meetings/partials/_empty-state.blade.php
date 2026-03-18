{{-- Usage: @include('meetings.partials._empty-state') --}}
@php
    $hasFilters = request()->hasAny(['search', 'status', 'project_id', 'date_from', 'date_to']);
@endphp

<div class="flex flex-col items-center justify-center py-20 text-center" role="status">
    @if($hasFilters)
        {{-- No results for active filters --}}
        <div class="w-16 h-16 rounded-2xl bg-gray-50 dark:bg-slate-700 flex items-center justify-center mb-5">
            <svg class="w-8 h-8 text-gray-300 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">No meetings match your filters</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5 max-w-xs">Try adjusting your search or clearing some filters to find what you're looking for.</p>
        <a href="{{ route('meetings.index', request('view') ? ['view' => request('view')] : []) }}"
           class="inline-flex items-center gap-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 px-4 py-2 rounded-xl text-sm font-medium transition-colors">
            Clear filters
        </a>
    @else
        {{-- No meetings at all --}}
        <div class="w-16 h-16 rounded-2xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center mb-5">
            <svg class="w-8 h-8 text-violet-400 dark:text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">No meetings yet</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5 max-w-xs">Schedule your first meeting to get started with AI-powered transcription and action items.</p>
        @can('create', \App\Domain\Meeting\Models\MinutesOfMeeting::class)
            <a href="{{ route('meetings.create') }}"
               class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Meeting
            </a>
        @endcan
    @endif
</div>
