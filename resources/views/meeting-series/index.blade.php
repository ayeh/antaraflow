@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Meeting Series</h1>
        <a href="{{ route('meeting-series.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Series
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($series->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-500 mb-4">No meeting series yet.</p>
            <a href="{{ route('meeting-series.create') }}" class="text-sm font-medium text-violet-600 hover:text-violet-700">Create your first series</a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach($series as $item)
                <div class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="min-w-0">
                            <a href="{{ route('meeting-series.show', $item) }}" class="text-sm font-semibold text-gray-900 hover:text-violet-600">{{ $item->name }}</a>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                @php
                                    $patternLabels = ['weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly'];
                                    $patternLabel = $patternLabels[$item->recurrence_pattern] ?? ucfirst($item->recurrence_pattern ?? 'None');
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ $patternLabel }}</span>
                                @if($item->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                                @endif
                                <span class="text-xs text-gray-400">{{ $item->meetings_count }} {{ Str::plural('meeting', $item->meetings_count) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                        <a href="{{ route('meeting-series.edit', $item) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">Edit</a>
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
