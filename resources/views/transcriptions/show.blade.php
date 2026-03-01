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
        @php
            $speakerColors = [
                'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
                'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
                'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300',
                'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
                'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
            ];
            $speakerColorMap = [];
            $speakerColorIndex = 0;
        @endphp

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Segments</h2>
            <div class="space-y-0">
                @foreach($transcription->segments as $segment)
                    @php
                        $startFormatted = sprintf('%02d:%02d', (int) floor($segment->start_time / 60), (int) ($segment->start_time % 60));
                        $endFormatted   = sprintf('%02d:%02d', (int) floor($segment->end_time / 60), (int) ($segment->end_time % 60));

                        if ($segment->speaker !== null && ! isset($speakerColorMap[$segment->speaker])) {
                            $speakerColorMap[$segment->speaker] = $speakerColors[$speakerColorIndex % count($speakerColors)];
                            $speakerColorIndex++;
                        }
                    @endphp

                    <div class="flex gap-4 py-4 {{ !$loop->last ? 'border-b border-gray-100 dark:border-slate-700' : '' }}">
                        {{-- Timestamp --}}
                        <div class="shrink-0 w-28 pt-0.5">
                            <span class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $startFormatted }} – {{ $endFormatted }}</span>
                        </div>

                        {{-- Main body --}}
                        <div class="flex-1 min-w-0 space-y-1">
                            @if($segment->speaker !== null)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $speakerColorMap[$segment->speaker] }}">
                                    {{ $segment->speaker }}
                                </span>
                            @endif
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $segment->text }}</p>
                        </div>

                        {{-- Right side: confidence + edited badge --}}
                        <div class="shrink-0 flex flex-col items-end gap-1 pt-0.5">
                            @if($segment->confidence !== null)
                                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono" title="Confidence">
                                    {{ number_format($segment->confidence * 100, 1) }}%
                                </span>
                            @endif
                            @if($segment->is_edited)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300">
                                    Edited
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
