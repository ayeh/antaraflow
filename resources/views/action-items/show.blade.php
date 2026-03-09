@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.action-items.index', $actionItem->meeting) }}" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $actionItem->title }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $actionItem->meeting->title }}</p>
            </div>
        </div>
        <a href="{{ route('meetings.action-items.edit', [$actionItem->meeting, $actionItem]) }}" class="bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">Edit</a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-6">
        <div class="flex flex-wrap gap-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @if($actionItem->status === \App\Support\Enums\ActionItemStatus::Open) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                @elseif($actionItem->status === \App\Support\Enums\ActionItemStatus::InProgress) bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300
                @elseif($actionItem->status === \App\Support\Enums\ActionItemStatus::Completed) bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                @elseif($actionItem->status === \App\Support\Enums\ActionItemStatus::Cancelled) bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300
                @elseif($actionItem->status === \App\Support\Enums\ActionItemStatus::CarriedForward) bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300
                @endif">
                {{ ucfirst(str_replace('_', ' ', $actionItem->status->value)) }}
            </span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @if($actionItem->priority === \App\Support\Enums\ActionItemPriority::Low) bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300
                @elseif($actionItem->priority === \App\Support\Enums\ActionItemPriority::Medium) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                @elseif($actionItem->priority === \App\Support\Enums\ActionItemPriority::High) bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300
                @elseif($actionItem->priority === \App\Support\Enums\ActionItemPriority::Critical) bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300
                @endif">
                {{ ucfirst($actionItem->priority->value) }} Priority
            </span>
        </div>

        @if($actionItem->description)
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ $actionItem->description }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-gray-200 dark:border-slate-700">
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assigned To</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $actionItem->assignedTo?->name ?? 'Unassigned' }}</p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $actionItem->due_date?->format('M j, Y') ?? 'No due date' }}</p>
            </div>
        </div>
    </div>

    {{-- Activity / History --}}
    @if($actionItem->histories->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Activity</h2>
            <ol class="relative border-l border-gray-200 dark:border-slate-600 space-y-5 ml-2">
                @foreach($actionItem->histories->sortByDesc('created_at') as $history)
                    <li class="ml-4">
                        {{-- Timeline dot --}}
                        <div class="absolute -left-1.5 mt-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800
                            {{ $history->comment ? 'bg-violet-500' : 'bg-gray-300 dark:bg-slate-500' }}">
                        </div>

                        <div class="flex flex-col gap-0.5">
                            {{-- Status change line --}}
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium">{{ $history->changedBy?->name ?? 'Someone' }}</span>
                                @if($history->old_value !== $history->new_value)
                                    changed status from
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400">
                                        {{ \App\Support\Enums\ActionItemStatus::tryFrom($history->old_value)?->label() ?? $history->old_value }}
                                    </span>
                                    <span class="text-gray-400 mx-0.5">→</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ \App\Support\Enums\ActionItemStatus::tryFrom($history->new_value)?->colorClass() ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ \App\Support\Enums\ActionItemStatus::tryFrom($history->new_value)?->label() ?? $history->new_value }}
                                    </span>
                                @else
                                    added a note
                                @endif
                            </p>

                            {{-- Note/comment --}}
                            @if($history->comment)
                                <p class="text-sm text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 mt-1">
                                    "{{ $history->comment }}"
                                </p>
                            @endif

                            {{-- Timestamp --}}
                            <time class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $history->created_at->diffForHumans() }} &middot; {{ $history->created_at->format('M j, Y H:i') }}
                            </time>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
@endsection
