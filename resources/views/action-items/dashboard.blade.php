@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items Dashboard</h1>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
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
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.action-items.show', [$item->meeting, $item]) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->title }}</a>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $item->meeting) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400">{{ $item->meeting->title }}</a>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($item->status === \App\Support\Enums\ActionItemStatus::Open) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                    @elseif($item->status === \App\Support\Enums\ActionItemStatus::InProgress) bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300
                                    @elseif($item->status === \App\Support\Enums\ActionItemStatus::Completed) bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                    @elseif($item->status === \App\Support\Enums\ActionItemStatus::Cancelled) bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300
                                    @elseif($item->status === \App\Support\Enums\ActionItemStatus::CarriedForward) bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $item->status->value)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($item->priority === \App\Support\Enums\ActionItemPriority::Low) bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300
                                    @elseif($item->priority === \App\Support\Enums\ActionItemPriority::Medium) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                    @elseif($item->priority === \App\Support\Enums\ActionItemPriority::High) bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300
                                    @elseif($item->priority === \App\Support\Enums\ActionItemPriority::Critical) bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300
                                    @endif">
                                    {{ ucfirst($item->priority->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->assignedTo?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->due_date?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No action items yet</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Action items from your meetings will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
