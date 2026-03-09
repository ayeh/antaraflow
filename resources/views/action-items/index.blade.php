@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items &mdash; {{ $meeting->title }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <x-action-items.view-toggle
                :currentView="$currentView"
                :tableUrl="route('meetings.action-items.index', [$meeting, 'view' => 'table'])"
                :kanbanUrl="route('meetings.action-items.index', [$meeting, 'view' => 'kanban'])"
            />
            <a href="{{ route('meetings.action-items.create', $meeting) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Action Item
            </a>
        </div>
    </div>

    @if($currentView === 'kanban')
        {{-- Kanban Board --}}
        <x-action-items.kanban-board :actionItems="$actionItems" :showMeeting="false" />
    @else
        {{-- Table --}}
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
                                        completed: @js($item->status === \App\Support\Enums\ActionItemStatus::Completed),
                                        priorityLabel: @js($item->priority->label()),
                                        priorityColorClass: @js($item->priority->colorClass()),
                                        assigneeName: @js($item->assignedTo?->name ?? '—'),
                                        dueDateFormatted: @js($item->due_date?->format('M j, Y') ?? '—'),
                                        dueDatePast: @js($item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed),
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
                                                fetch('{{ route('meetings.action-items.status', [$meeting, $item]) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                        'Accept': 'application/json',
                                                    },
                                                    body: JSON.stringify({ status: completed ? 'completed' : 'open' }),
                                                }).then(res => {
                                                    if (res.ok) {
                                                        $dispatch('action-item-status-changed', { id: {{ $item->id }}, status: completed ? 'completed' : 'open' });
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
                                            @click="$dispatch('open-slide-over', { meetingId: {{ $meeting->id }}, itemId: {{ $item->id }} })"
                                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 text-left"
                                        >{{ $item->title }}</button>
                                    </td>

                                    {{-- Inline Status Badge --}}
                                    <td class="px-6 py-4">
                                        <x-action-item-status-badge :item="$item" :meeting="$meeting" />
                                    </td>

                                    <td class="px-6 py-4">
                                        <span :class="priorityColorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="priorityLabel"></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" x-text="assigneeName"></td>
                                    <td class="px-6 py-4 text-sm" :class="dueDatePast ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'" x-text="dueDateFormatted"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No action items yet.</td>
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

{{-- Slide-over --}}
<x-action-items.slide-over />
@endsection
