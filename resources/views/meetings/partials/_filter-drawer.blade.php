{{-- Usage: @include('meetings.partials._filter-drawer', ['projects' => $projects]) --}}
{{-- Requires Alpine state: filterOpen (bool) on parent --}}

{{-- Backdrop --}}
<div x-show="filterOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="filterOpen = false"
     class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40"
     x-cloak></div>

{{-- Drawer --}}
<div x-show="filterOpen"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     @keydown.escape.window="filterOpen = false"
     role="dialog" aria-modal="true" aria-label="Filter meetings"
     class="fixed right-0 top-0 bottom-0 w-full sm:w-96 bg-white dark:bg-slate-800 shadow-2xl z-50 flex flex-col"
     x-cloak>

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Filter Meetings</h2>
        <button @click="filterOpen = false"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                aria-label="Close filter drawer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Filter form --}}
    <form method="GET" action="{{ route('meetings.index') }}" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
        {{-- Preserve view mode --}}
        @if(request('view'))
            <input type="hidden" name="view" value="{{ request('view') }}">
        @endif

        {{-- Search --}}
        <div>
            <label for="drawer-search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Search</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text" id="drawer-search" name="search" value="{{ request('search') }}"
                       placeholder="Title or MOM number..."
                       class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg pl-9 pr-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-colors">
            </div>
        </div>

        {{-- Project --}}
        <div>
            <label for="drawer-project" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Project</label>
            <div class="relative">
                <select id="drawer-project" name="project_id"
                        class="w-full appearance-none bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 pr-9 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-colors">
                    <option value="">All Projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->name }} ({{ $project->code }})
                        </option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div>
            <label for="drawer-status" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
            <div class="relative">
                <select id="drawer-status" name="status"
                        class="w-full appearance-none bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 pr-9 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-colors">
                    <option value="">All Statuses</option>
                    @foreach(\App\Support\Enums\MeetingStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status->value)) }}
                        </option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Date range --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="drawer-date-from" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">From</label>
                <input type="date" id="drawer-date-from" name="date_from" value="{{ request('date_from') }}"
                       class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-colors">
            </div>
            <div>
                <label for="drawer-date-to" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">To</label>
                <input type="date" id="drawer-date-to" name="date_to" value="{{ request('date_to') }}"
                       class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-colors">
            </div>
        </div>

        {{-- Sticky footer with actions --}}
        <div class="sticky bottom-0 bg-white dark:bg-slate-800 pt-4 pb-1 border-t border-gray-200 dark:border-slate-700 -mx-6 px-6 mt-6 flex gap-3">
            <button type="submit"
                    class="flex-1 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                Apply Filters
            </button>
            <a href="{{ route('meetings.index', request('view') ? ['view' => request('view')] : []) }}"
               class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl transition-colors">
                Clear
            </a>
        </div>
    </form>
</div>
