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
        <div class="flex items-center gap-3">
            {{-- AI Speaker Diarization Button --}}
            @can('update', $meeting)
                @if($transcription->status->value === 'completed' && $transcription->segments->isNotEmpty())
                    <form method="POST" action="{{ route('meetings.transcriptions.diarize', [$meeting, $transcription]) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20 rounded-lg hover:bg-violet-100 dark:hover:bg-violet-900/40 transition-colors"
                                onclick="return confirm('Run AI speaker analysis? This will re-label speakers based on conversation context. Manual edits will be preserved.')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                            </svg>
                            AI Analyze Speakers
                        </button>
                    </form>
                @endif
            @endcan

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
    </div>

    {{-- Speaker Timeline --}}
    @if($transcription->segments->whereNotNull('speaker')->isNotEmpty() && $transcription->duration_seconds)
        @php
            $timelineColors = [
                'bg-blue-400',
                'bg-violet-400',
                'bg-amber-400',
                'bg-rose-400',
                'bg-teal-400',
                'bg-indigo-400',
            ];
            $uniqueSpeakers = $transcription->segments->pluck('speaker')->filter()->unique()->values();
            $speakerTimelineColorMap = [];
            foreach ($uniqueSpeakers as $idx => $spk) {
                $speakerTimelineColorMap[$spk] = $timelineColors[$idx % count($timelineColors)];
            }
            $duration = $transcription->duration_seconds;
        @endphp

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6" id="speaker-timeline">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Speaker Timeline</h2>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-3 mb-3">
                @foreach($uniqueSpeakers as $spk)
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-full {{ $speakerTimelineColorMap[$spk] }}"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $spk }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Timeline bar --}}
            <div class="relative w-full h-6 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                @foreach($transcription->segments->whereNotNull('speaker') as $seg)
                    @php
                        $left  = round(($seg->start_time / $duration) * 100, 2);
                        $width = round((($seg->end_time - $seg->start_time) / $duration) * 100, 2);
                        $color = $speakerTimelineColorMap[$seg->speaker] ?? 'bg-gray-400';
                        $startFmt = sprintf('%02d:%02d', intdiv((int)$seg->start_time, 60), (int)$seg->start_time % 60);
                        $endFmt   = sprintf('%02d:%02d', intdiv((int)$seg->end_time, 60), (int)$seg->end_time % 60);
                    @endphp
                    <div
                        class="absolute h-full {{ $color }} opacity-80 hover:opacity-100 transition-opacity cursor-pointer group"
                        style="left: {{ $left }}%; width: {{ max($width, 0.5) }}%"
                        title="{{ $seg->speaker }}: {{ $startFmt }} – {{ $endFmt }}"
                    >
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10 pointer-events-none">
                            <div class="bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap shadow">
                                {{ $seg->speaker }}: {{ $startFmt }}–{{ $endFmt }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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
                'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300',
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
                        $startSecs      = (int) floor($segment->start_time);
                        $endSecs        = (int) floor($segment->end_time);
                        $startFormatted = sprintf('%02d:%02d', intdiv($startSecs, 60), $startSecs % 60);
                        $endFormatted   = sprintf('%02d:%02d', intdiv($endSecs, 60), $endSecs % 60);

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
                                <span
                                    x-data="{
                                        editing: false,
                                        value: @js($segment->speaker),
                                        original: @js($segment->speaker),
                                        save() {
                                            if (this.value.trim() === '' || this.value.trim() === this.original) {
                                                this.editing = false;
                                                this.value = this.original;
                                                return;
                                            }
                                            const newVal = this.value.trim();
                                            fetch('{{ route('meetings.transcriptions.speakers.update', [$meeting, $transcription]) }}', {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                    'Accept': 'application/json',
                                                },
                                                body: JSON.stringify({ old_speaker: this.original, new_speaker: newVal }),
                                            }).then(r => {
                                                if (r.ok) {
                                                    document.querySelectorAll('[data-speaker]').forEach(el => {
                                                        const data = Alpine.$data(el);
                                                        if (data && data.original === this.original) {
                                                            data.original = newVal;
                                                            data.value = newVal;
                                                        }
                                                    });
                                                    this.original = newVal;
                                                }
                                                this.editing = false;
                                            });
                                        }
                                    }"
                                    data-speaker="{{ $segment->speaker }}"
                                >
                                    <template x-if="!editing">
                                        <button
                                            @click="editing = true"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium {{ $speakerColorMap[$segment->speaker] }} hover:ring-2 hover:ring-offset-1 hover:ring-blue-400 transition-all cursor-pointer"
                                            title="Click to rename speaker"
                                        >
                                            <span x-text="value"></span>
                                            <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                        </button>
                                    </template>
                                    <template x-if="editing">
                                        <input
                                            x-model="value"
                                            @keydown.enter="save()"
                                            @keydown.escape="editing = false; value = original"
                                            @blur="save()"
                                            x-init="$nextTick(() => $el.focus())"
                                            class="inline-flex px-2 py-0.5 rounded text-xs font-medium border border-blue-400 outline-none w-28 bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                                            maxlength="100"
                                        />
                                    </template>
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
