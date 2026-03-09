@props(['selectedStatuses' => [], 'selectedPriorities' => [], 'assigneeFilter' => null])

@php
    $statusOptions = \App\Support\Enums\ActionItemStatus::cases();
    $priorityOptions = \App\Support\Enums\ActionItemPriority::cases();

    $activeStatusValues = array_map(fn ($s) => $s instanceof \App\Support\Enums\ActionItemStatus ? $s->value : $s, $selectedStatuses);
    $activePriorityValues = array_map(fn ($p) => $p instanceof \App\Support\Enums\ActionItemPriority ? $p->value : $p, $selectedPriorities);

    $hasActiveFilters = ! empty($activeStatusValues) || ! empty($activePriorityValues) || $assigneeFilter === 'me';
@endphp

<form
    method="GET"
    action="{{ route('action-items.dashboard') }}"
    x-data="{
        statuses: @js($activeStatusValues),
        priorities: @js($activePriorityValues),
        assignee: '{{ $assigneeFilter ?? '' }}',
        toggleStatus(val) {
            this.statuses.includes(val)
                ? this.statuses = this.statuses.filter(s => s !== val)
                : this.statuses.push(val);
            this.$nextTick(() => this.$el.submit());
        },
        togglePriority(val) {
            this.priorities.includes(val)
                ? this.priorities = this.priorities.filter(p => p !== val)
                : this.priorities.push(val);
            this.$nextTick(() => this.$el.submit());
        },
        toggleAssignee() {
            this.assignee = this.assignee === 'me' ? '' : 'me';
            this.$nextTick(() => this.$el.submit());
        },
    }"
>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <div class="flex flex-wrap items-center gap-4">

            {{-- Status Filter --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</span>
                @foreach ($statusOptions as $status)
                    <button
                        type="button"
                        @click="toggleStatus('{{ $status->value }}')"
                        :class="statuses.includes('{{ $status->value }}')
                            ? '{{ $status->colorClass() }} ring-2 ring-offset-1 ring-violet-500'
                            : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                    >
                        {{ $status->label() }}
                    </button>
                    <input type="hidden" name="status[]" value="{{ $status->value }}" x-show="statuses.includes('{{ $status->value }}')">
                @endforeach
            </div>

            <div class="h-5 w-px bg-gray-200 dark:bg-slate-600 hidden sm:block"></div>

            {{-- Priority Filter --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</span>
                @foreach ($priorityOptions as $priority)
                    <button
                        type="button"
                        @click="togglePriority('{{ $priority->value }}')"
                        :class="priorities.includes('{{ $priority->value }}')
                            ? '{{ $priority->colorClass() }} ring-2 ring-offset-1 ring-violet-500'
                            : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                    >
                        {{ $priority->label() }}
                    </button>
                    <input type="hidden" name="priority[]" value="{{ $priority->value }}" x-show="priorities.includes('{{ $priority->value }}')">
                @endforeach
            </div>

            <div class="h-5 w-px bg-gray-200 dark:bg-slate-600 hidden sm:block"></div>

            {{-- Assignee Filter --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</span>
                <button
                    type="button"
                    @click="toggleAssignee()"
                    :class="assignee === 'me' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300 ring-2 ring-offset-1 ring-violet-500' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                >
                    Assigned to me
                </button>
                <input type="hidden" name="assignee" value="me" x-show="assignee === 'me'">
            </div>

            {{-- Clear All --}}
            @if ($hasActiveFilters)
                <a href="{{ route('action-items.dashboard') }}" class="ml-auto text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    Clear all
                </a>
            @endif

        </div>
    </div>
</form>
