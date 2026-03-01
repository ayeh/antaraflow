@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $transcription->original_filename }}</h1>
            </div>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
            @if($transcription->status->value === 'completed') bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
            @elseif($transcription->status->value === 'processing') bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
            @elseif($transcription->status->value === 'failed') bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
            @else bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300
            @endif
        ">
            {{ ucfirst($transcription->status->value) }}
        </span>
    </div>

    @if($transcription->full_text)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Full Transcript</h2>
            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $transcription->full_text }}</div>
        </div>
    @endif

    @if($transcription->segments->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Segments</h2>
            <div class="space-y-3">
                @foreach($transcription->segments as $index => $segment)
                    <div class="flex gap-4 py-3 {{ !$loop->last ? 'border-b border-gray-100 dark:border-slate-700' : '' }}">
                        <span class="text-xs font-mono text-gray-400 dark:text-gray-500 pt-0.5 shrink-0">{{ str_pad($index + 1, 3, '0', STR_PAD_LEFT) }}</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $segment->text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
