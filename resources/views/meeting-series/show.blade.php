@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meeting-series.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $meetingSeries->name }}</h1>
                    @php
                        $patternLabels = ['weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly'];
                        $patternLabel = $patternLabels[$meetingSeries->recurrence_pattern] ?? ucfirst($meetingSeries->recurrence_pattern ?? 'None');
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ $patternLabel }}</span>
                    @if($meetingSeries->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">Inactive</span>
                    @endif
                </div>
                @if($meetingSeries->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $meetingSeries->description }}</p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('meeting-series.edit', $meetingSeries) }}" class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Edit</a>
            <form method="POST" action="{{ route('meeting-series.destroy', $meetingSeries) }}" onsubmit="return confirm('Are you sure you want to delete this series?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-white dark:bg-slate-800 border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Delete</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Related Meetings</h2>
                @if($meetingSeries->meetings->isEmpty())
                    <p class="text-sm text-gray-400 dark:text-gray-500">No meetings generated yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($meetingSeries->meetings as $meeting)
                            <li class="py-3 flex items-center justify-between">
                                <div class="min-w-0">
                                    <a href="{{ route('meetings.show', $meeting) }}" class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:text-violet-600 dark:hover:text-violet-400">{{ $meeting->title }}</a>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $meeting->meeting_date->format('M j, Y') }}</p>
                                </div>
                                @php
                                    $statusColors = [
                                        'draft'       => 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400',
                                        'in_progress' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
                                        'finalized'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                                        'approved'    => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                                    ];
                                    $statusColor = $statusColors[$meeting->status->value] ?? 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }} ml-4 flex-shrink-0">
                                    {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Generate Meetings</h2>
                <form method="POST" action="{{ route('meeting-series.generate', $meetingSeries) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Number of meetings</label>
                        <input type="number" name="count" id="count" value="3" min="1" max="12" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    </div>
                    <button type="submit" class="w-full bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                        Generate Meetings
                    </button>
                </form>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Details</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Pattern</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $patternLabel }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingSeries->is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Meetings</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingSeries->meetings->count() }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingSeries->created_at->format('M j, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
