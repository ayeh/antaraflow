@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items</h1>
        <x-action-items.view-toggle
            :currentView="$currentView"
            :tableUrl="route('action-items.dashboard', array_merge(request()->query(), ['view' => 'table']))"
            :kanbanUrl="route('action-items.dashboard', array_merge(request()->query(), ['view' => 'kanban']))"
        />
    </div>

    {{-- Filter Bar (table view only) --}}
    @if($currentView === 'table')
        <x-action-items.filter-bar
            :selectedStatuses="$selectedStatuses"
            :selectedPriorities="$selectedPriorities"
            :assigneeFilter="$assigneeFilter"
        />
    @endif

    @if($currentView === 'kanban')
        {{-- Kanban Board --}}
        <x-action-items.kanban-board :actionItems="$actionItems" :showMeeting="true" />
    @else
        {{-- Table with bulk selection --}}
        <div
            x-data="{
                selected: [],
                totalItems: {{ $actionItems->count() }},
                get allSelected() { return this.selected.length === this.totalItems && this.totalItems > 0; },
                toggleAll() {
                    if (this.allSelected) {
                        this.selected = [];
                    } else {
                        this.selected = [{{ $actionItems->pluck('id')->join(', ') }}];
                    }
                },
                toggle(id) {
                    this.selected.includes(id)
                        ? this.selected = this.selected.filter(i => i !== id)
                        : this.selected.push(id);
                },
                async applyBulk(action, value = null) {
                    if (this.selected.length === 0) { return; }
                    if (action === 'delete' && !confirm(`Delete ${this.selected.length} item(s)? This cannot be undone.`)) { return; }
                    try {
                        const res = await fetch('{{ route('action-items.bulk') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            },
                            body: JSON.stringify({ ids: this.selected, action, value }),
                        });
                        if (!res.ok) { throw new Error('Failed'); }
                        window.location.reload();
                    } catch {
                        alert('Bulk action failed. Please try again.');
                    }
                }
            }"
        >
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        :indeterminate="selected.length > 0 && !allSelected"
                                        @change="toggleAll()"
                                        class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                    >
                                </th>
                                <th class="w-10 px-2 py-3"></th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meeting</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @forelse($actionItems as $item)
                                <tr
                                    class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
                                    x-data="{
                                        completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                        priorityLabel: @js($item->priority->label()),
                                        priorityColorClass: @js($item->priority->colorClass()),
                                        assigneeName: @js($item->assignedTo?->name ?? '—'),
                                        dueDateFormatted: @js($item->due_date?->format('M j, Y') ?? '—'),
                                        dueDatePast: {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                    }"
                                    :class="completed ? 'opacity-60' : ''"
                                    @action-item-updated.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            priorityLabel = $event.detail.priority_label;
                                            priorityColorClass = $event.detail.priority_color_class;
                                            assigneeName = $event.detail.assigned_to_name ?? '—';
                                            dueDateFormatted = $event.detail.due_date_formatted ?? '—';
                                            dueDatePast = $event.detail.due_date_past ?? false;
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                    @action-item-status-changed.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                >
                                    {{-- Select checkbox --}}
                                    <td class="px-4 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="selected.includes({{ $item->id }})"
                                            @change="toggle({{ $item->id }})"
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    {{-- Quick Complete Checkbox --}}
                                    <td class="px-2 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="completed"
                                            @change="
                                                completed = !completed;
                                                const newStatus = completed ? 'completed' : 'open';
                                                fetch('{{ route('meetings.action-items.status', [$item->meeting, $item]) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                        'Accept': 'application/json',
                                                    },
                                                    body: JSON.stringify({ status: newStatus }),
                                                }).then(res => {
                                                    if (res.ok) {
                                                        $dispatch('action-item-status-changed', { id: {{ $item->id }}, status: newStatus });
                                                    } else {
                                                        completed = !completed;
                                                        alert('Failed to update. Please try again.');
                                                    }
                                                }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                            "
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    <td class="px-6 py-4">
                                        <button
                                            type="button"
                                            @click="$dispatch('open-slide-over', { meetingId: {{ $item->meeting->id }}, itemId: {{ $item->id }} })"
                                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 text-left"
                                        >{{ $item->title }}</button>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('meetings.show', $item->meeting) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400">{{ $item->meeting->title }}</a>
                                    </td>

                                    {{-- Inline Status Badge --}}
                                    <td class="px-6 py-4">
                                        <x-action-item-status-badge :item="$item" :meeting="$item->meeting" />
                                    </td>

                                    <td class="px-6 py-4">
                                        <span :class="priorityColorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="priorityLabel"></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" x-text="assigneeName"></td>
                                    <td class="px-6 py-4 text-sm" :class="dueDatePast ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'" x-text="dueDateFormatted"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-16 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                        @if($selectedStatuses || $selectedPriorities || $assigneeFilter)
                                            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No items match your filters</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('action-items.dashboard') }}" class="text-violet-600 hover:underline">Clear filters</a> to see all items.</p>
                                        @else
                                            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No action items yet</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Action items from your meetings will appear here.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Floating Bulk Action Bar --}}
            <div
                x-show="selected.length > 0"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-xl px-4 py-3"
                style="display: none;"
            >
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="selected.length + ' selected'"></span>
                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>

                <select
                    @change="if ($event.target.value) { applyBulk('status', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set status…</option>
                    @foreach(\App\Support\Enums\ActionItemStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>

                <select
                    @change="if ($event.target.value) { applyBulk('priority', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set priority…</option>
                    @foreach(\App\Support\Enums\ActionItemPriority::cases() as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    @click="applyBulk('delete')"
                    class="text-sm font-medium px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                >
                    Delete
                </button>

                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>
                <button type="button" @click="selected = []" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>

{{-- Slide-over (shared between table and kanban) --}}
<x-action-items.slide-over />
@endsection
