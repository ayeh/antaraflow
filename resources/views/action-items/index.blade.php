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
        <a href="{{ route('meetings.action-items.create', $meeting) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Action Item
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
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
                                        fetch('{{ route('meetings.action-items.status', [$meeting, $item]) }}', {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({ status: completed ? 'completed' : 'open' }),
                                        }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                    "
                                    class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                >
                            </td>

                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.action-items.show', [$meeting, $item]) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->title }}</a>
                            </td>

                            {{-- Inline Status Badge --}}
                            <td class="px-6 py-4">
                                <x-action-item-status-badge :item="$item" :meeting="$meeting" />
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
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('meetings.action-items.edit', [$meeting, $item]) }}" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 font-medium">Edit</a>
                            </td>
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
</div>
@endsection
