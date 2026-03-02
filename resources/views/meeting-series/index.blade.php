@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Meeting Series</h1>
        <a href="{{ route('meeting-series.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Series
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($series->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-6 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No meeting series yet</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Group related meetings into a series to track recurring topics.</p>
            <div class="mt-6">
                <a href="{{ route('meeting-series.create') }}" class="inline-flex items-center rounded-md bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    New Series
                </a>
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 divide-y divide-gray-100 dark:divide-slate-700">
            @foreach($series as $item)
                <div class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="min-w-0">
                            <a href="{{ route('meeting-series.show', $item) }}" class="text-sm font-semibold text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->name }}</a>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                @php
                                    $patternLabels = ['weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly'];
                                    $patternLabel = $patternLabels[$item->recurrence_pattern] ?? ucfirst($item->recurrence_pattern ?? 'None');
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ $patternLabel }}</span>
                                @if($item->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">Inactive</span>
                                @endif
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $item->meetings_count }} {{ Str::plural('meeting', $item->meetings_count) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                        <a href="{{ route('meeting-series.edit', $item) }}" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Edit</a>
                        <form method="POST" action="{{ route('meeting-series.destroy', $item) }}" onsubmit="return confirm('Are you sure you want to delete this series?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
