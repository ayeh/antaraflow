@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items</h1>
    </div>

    {{-- Filter Bar --}}
    <x-action-items.filter-bar
        :selectedStatuses="$selectedStatuses"
        :selectedPriorities="$selectedPriorities"
        :assigneeFilter="$assigneeFilter"
    />

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
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
                            x-data="{ completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }} }"
                            :class="completed ? 'opacity-60' : ''"
                        >
                            {{-- Quick Complete Checkbox --}}
                            <td class="px-4 py-4">
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
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
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
                                <a href="{{ route('meetings.action-items.show', [$item->meeting, $item]) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->title }}</a>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $item->meeting) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400">{{ $item->meeting->title }}</a>
                            </td>

                            {{-- Inline Status Badge --}}
                            <td class="px-6 py-4">
                                <x-action-item-status-badge :item="$item" :meeting="$item->meeting" />
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->priority->colorClass() }}">
                                    {{ $item->priority->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->assignedTo?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $item->due_date?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
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
</div>
@endsection
