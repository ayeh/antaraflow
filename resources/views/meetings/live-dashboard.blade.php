@extends('layouts.app')

@section('title', __('Live Dashboard') . ' - ' . $meeting->title)

@section('content')
<div class="max-w-[1600px] mx-auto"
     @recording-complete.window="handleRecordingComplete($event.detail)"
     x-data="liveMeetingDashboard({
        meetingId: {{ $meeting->id }},
        sessionId: {{ $session->id }},
        stateUrl: '{{ route('meetings.live.state', [$meeting, $session]) }}',
        chunkUrl: '{{ route('meetings.live.chunk', [$meeting, $session]) }}',
        endUrl: '{{ route('meetings.live.end', [$meeting, $session]) }}',
        extractionUrl: '{{ route('meetings.live.extraction', [$meeting, $session]) }}',
        liveExtraction: {!! \Illuminate\Support\Js::from((bool) ($session->config['live_extraction'] ?? false)) !!},
        meetingUrl: '{{ route('meetings.show', $meeting) }}',
        initialChunks: {!! \Illuminate\Support\Js::from($state['chunks']) !!},
        initialExtractions: {!! \Illuminate\Support\Js::from($state['extractions']) !!},
        sessionStatus: {!! \Illuminate\Support\Js::from($session->status->value) !!},
        i18n: {
            confirmEndSession: '{{ __('Are you sure you want to end this live session? This action cannot be undone.') }}',
            failedEndSession: '{{ __('Failed to end session. Please try again.') }}',
            networkError: '{{ __('Network error. Please check your connection and try again.') }}',
            notYetUpdated: '{{ __('Not yet updated') }}',
            extractionToggleFailed: '{{ __('Could not change the AI extraction setting. Please try again.') }}',
        },
        startedAt: {!! \Illuminate\Support\Js::from($session->started_at?->toIso8601String()) !!},
        attendees: {!! \Illuminate\Support\Js::from($meeting->attendees()->with('user')->get()) !!},
     })">

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a :href="meetingUrl"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Back to Meeting') }}
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $meeting->title }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $meeting->mom_number }}</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Live Indicator --}}
            <div x-show="sessionStatus === 'active'" class="flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
                <span class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wide">{{ __('Live') }}</span>
            </div>

            {{-- Session Status Badge --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                  :class="statusColor"
                  x-text="statusLabel">
            </span>

            {{-- Elapsed Timer --}}
            <span class="text-sm font-mono text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-slate-800 px-3 py-1 rounded-lg"
                  x-text="formatTime(elapsedSeconds)">
            </span>
        </div>
    </div>

    {{-- Three-Panel Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Panel 1: Live Transcript Feed --}}
        <div class="lg:col-span-5">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col h-[70vh]">
                {{-- Panel Header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Live Transcript') }}</h2>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400"
                          x-text="transcriptChunks.length + ' segment' + (transcriptChunks.length !== 1 ? 's' : '')">
                    </span>
                </div>

                {{-- Transcript Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3"
                     x-ref="transcriptContainer"
                     @scroll="handleScroll()">

                    {{-- Empty State --}}
                    <div x-show="transcriptChunks.length === 0" class="flex flex-col items-center justify-center h-full text-center">
                        <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Waiting for audio transcription...') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Transcripts will appear here as audio is processed.') }}</p>
                    </div>

                    {{-- Transcript Chunks --}}
                    <template x-for="chunk in transcriptChunks" :key="chunk.id">
                        <div class="group rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-semibold"
                                      :class="speakerColor(chunk.speaker)"
                                      x-text="chunk.speaker || '{{ __('Speaker') }}'">
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500"
                                      x-text="formatTimestamp(chunk.start_time) + ' - ' + formatTimestamp(chunk.end_time)">
                                </span>
                                <span x-show="chunk.confidence"
                                      class="text-xs text-gray-400 dark:text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                      x-text="formatConfidence(chunk.confidence)">
                                </span>
                            </div>
                            <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed" x-text="chunk.text"></p>
                        </div>
                    </template>

                    {{-- Processing Indicator --}}
                    <div x-show="sessionStatus === 'active'" class="flex items-center gap-2 px-3 py-2 text-gray-400 dark:text-gray-500">
                        <div class="flex gap-1">
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-slate-500 rounded-full animate-bounce" style="animation-delay: 0ms;"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-slate-500 rounded-full animate-bounce" style="animation-delay: 150ms;"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 dark:bg-slate-500 rounded-full animate-bounce" style="animation-delay: 300ms;"></span>
                        </div>
                        <span class="text-xs">{{ __('Listening...') }}</span>
                    </div>
                </div>

                {{-- Jump to Latest Button --}}
                <div x-show="!isAutoScroll && transcriptChunks.length > 0"
                     x-cloak
                     class="px-5 py-2 border-t border-gray-200 dark:border-slate-700">
                    <button @click="isAutoScroll = true; scrollToLatest()"
                            class="w-full text-center text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors">
                        <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        {{ __('Jump to latest') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Panel 2: AI Extractions --}}
        <div class="lg:col-span-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col h-[70vh]">
                {{-- Panel Header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('AI Extractions') }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- On/off for live extraction. Off by default: each run
                             costs about what a full set of minutes costs. --}}
                        @can('startLive', $meeting)
                            <button type="button"
                                    @click="toggleLiveExtraction()"
                                    :disabled="togglingExtraction || sessionStatus !== 'active'"
                                    :class="liveExtraction
                                        ? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700'
                                        : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'"
                                    class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-lg border text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    :title="liveExtraction
                                        ? '{{ __('AI is summarising as the meeting runs. Click to stop.') }}'
                                        : '{{ __('Have AI summarise as the meeting runs (costs extra).') }}'">
                                <span class="w-1.5 h-1.5 rounded-full"
                                      :class="liveExtraction ? 'bg-white animate-pulse' : 'bg-gray-400'"></span>
                                <span x-text="liveExtraction ? '{{ __('Live AI on') }}' : '{{ __('Live AI off') }}'"></span>
                            </button>
                        @endcan

                        {{-- Loading Spinner --}}
                        <svg x-show="isExtracting" x-cloak class="animate-spin w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="'{{ __('Updated:') }} ' + formatLastUpdate()"></span>
                    </div>
                </div>

                {{-- Extractions Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    {{-- Empty State --}}
                    <div x-show="!hasExtractions" class="flex flex-col items-center justify-center h-full text-center">
                        <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No AI extractions yet') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                           x-text="liveExtraction
                                ? '{{ __('Decisions, action items, and topics will appear as the AI processes the transcript.') }}'
                                : '{{ __('Turn on Live AI above to have decisions and action items appear as the meeting runs.') }}'"></p>
                    </div>

                    {{-- Summary Section --}}
                    <template x-if="summary.length > 0">
                        <div>
                            <button @click="toggleSection('summary')"
                                    class="flex items-center justify-between w-full text-left mb-2">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Summary') }}</h3>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': !isSectionCollapsed('summary') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="!isSectionCollapsed('summary')" x-collapse>
                                <template x-for="extraction in summary" :key="extraction.id">
                                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-4 py-3 mb-2">
                                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed" x-text="extraction.content"></p>
                                        <div x-show="extraction.confidence_score" class="mt-2 flex items-center gap-1">
                                            <div class="flex-1 h-1 bg-gray-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                                <div class="h-full bg-indigo-500 rounded-full" :style="'width: ' + (extraction.confidence_score * 100) + '%'"></div>
                                            </div>
                                            <span class="text-xs text-gray-400" x-text="formatConfidence(extraction.confidence_score)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Decisions Section --}}
                    <template x-if="decisions.length > 0">
                        <div>
                            <button @click="toggleSection('decisions')"
                                    class="flex items-center justify-between w-full text-left mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Decisions') }}</h3>
                                    <span class="text-xs text-gray-400 bg-gray-100 dark:bg-slate-700 px-1.5 py-0.5 rounded" x-text="decisions.length"></span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': !isSectionCollapsed('decisions') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="!isSectionCollapsed('decisions')" x-collapse>
                                <template x-for="extraction in decisions" :key="extraction.id">
                                    <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/20 rounded-lg px-4 py-3 mb-2">
                                        <p class="text-sm text-gray-800 dark:text-gray-200" x-text="extraction.content"></p>
                                        <div x-show="extraction.confidence_score" class="mt-2 flex items-center gap-1">
                                            <div class="flex-1 h-1 bg-blue-100 dark:bg-blue-900/30 rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-500 rounded-full" :style="'width: ' + (extraction.confidence_score * 100) + '%'"></div>
                                            </div>
                                            <span class="text-xs text-blue-500 dark:text-blue-400" x-text="formatConfidence(extraction.confidence_score)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Action Items Section --}}
                    <template x-if="actionItems.length > 0">
                        <div>
                            <button @click="toggleSection('action_items')"
                                    class="flex items-center justify-between w-full text-left mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Action Items') }}</h3>
                                    <span class="text-xs text-gray-400 bg-gray-100 dark:bg-slate-700 px-1.5 py-0.5 rounded" x-text="actionItems.length"></span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': !isSectionCollapsed('action_items') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="!isSectionCollapsed('action_items')" x-collapse>
                                <template x-for="extraction in actionItems" :key="extraction.id">
                                    <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/20 rounded-lg px-4 py-3 mb-2">
                                        <p class="text-sm text-gray-800 dark:text-gray-200" x-text="extraction.content"></p>
                                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                                            <template x-if="extraction.structured_data?.assignee">
                                                <span class="inline-flex items-center gap-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                    <span x-text="extraction.structured_data.assignee"></span>
                                                </span>
                                            </template>
                                            <template x-if="extraction.structured_data?.priority">
                                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                                      :class="priorityColor(extraction.structured_data.priority)"
                                                      x-text="extraction.structured_data.priority">
                                                </span>
                                            </template>
                                            <span x-show="extraction.confidence_score" class="text-xs text-gray-400" x-text="formatConfidence(extraction.confidence_score)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Topics / Key Points Section --}}
                    <template x-if="topics.length > 0">
                        <div>
                            <button @click="toggleSection('topics')"
                                    class="flex items-center justify-between w-full text-left mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 bg-violet-500 rounded-full"></span>
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Key Topics') }}</h3>
                                    <span class="text-xs text-gray-400 bg-gray-100 dark:bg-slate-700 px-1.5 py-0.5 rounded" x-text="topics.length"></span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': !isSectionCollapsed('topics') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="!isSectionCollapsed('topics')" x-collapse>
                                <template x-for="extraction in topics" :key="extraction.id">
                                    <div class="bg-violet-50 dark:bg-violet-900/10 border border-violet-100 dark:border-violet-900/20 rounded-lg px-4 py-3 mb-2">
                                        <p class="text-sm text-gray-800 dark:text-gray-200" x-text="extraction.content"></p>
                                        <span x-show="extraction.confidence_score" class="text-xs text-violet-500 dark:text-violet-400 mt-1 inline-block" x-text="formatConfidence(extraction.confidence_score)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Panel 3: Meeting Controls --}}
        <div class="lg:col-span-3">
            <div class="space-y-4">

                {{-- Session Timer Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-5">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">{{ __('Session Timer') }}</h3>
                    <div class="text-center">
                        <div class="text-4xl font-mono font-bold text-gray-900 dark:text-white mb-2"
                             x-text="formatTime(elapsedSeconds)">
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              :class="statusColor"
                              x-text="statusLabel">
                        </span>
                    </div>
                </div>

                {{-- Recording Controls Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-5">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">{{ __('Recording Controls') }}</h3>

                    {{-- Active / Paused Controls --}}
                    <div x-show="sessionStatus !== 'ended'" class="space-y-3">
                        {{-- Audio Recorder Integration --}}
                        <div x-data="audioRecorder({
                            uploadUrl: '',
                            chunkUrl: '',
                            finalizeUrl: '',
                            cancelUrl: '',
                            meetingId: {{ $meeting->id }},
                            liveMode: true,
                            liveChunkUrl: '{{ route('meetings.live.chunk', [$meeting, $session]) }}',
                            liveSessionId: {{ $session->id }},
                            initialChunkCount: {{ $state['chunks']->count() }},
                            consentUrl: '{{ route('meetings.recording-consent.store', $meeting) }}',
                            consentGiven: {{ ($recordingConsented ?? false) ? 'true' : 'false' }},
                            consentNoticeVersion: 'v1',
                            i18n: {
                                recordingInProgress: '{{ __('Recording is in progress. Are you sure you want to leave?') }}',
                                micDenied: '{{ __('Microphone access denied. Please allow microphone access in your browser settings.') }}',
                                micNotFound: '{{ __('No microphone found. Please connect a microphone and try again.') }}',
                                micInUse: '{{ __('Microphone is in use by another application. Please close it and try again.') }}',
                                micError: '{{ __('Could not access microphone. Please try again.') }}',
                                recordingFailed: '{{ __('Recording failed. Please try again.') }}',
                                recordingStopped: '{{ __('Recording stopped. Audio chunks have been sent for transcription.') }}',
                                noAudioData: '{{ __('Recording produced no audio data. Please try again.') }}',
                                recordingUploaded: '{{ __('Recording uploaded. Transcription in progress...') }}',
                                finalizeFailed: '{{ __('Failed to finalize recording. Please try again.') }}',
                                uploadRetrying: '{{ __('Upload failed. Retrying in {seconds}s...') }}',
                                uploadFailed: '{{ __('Upload failed after multiple attempts. Your recording is saved locally — click Retry to try again.') }}',
                                recoverFailed: '{{ __('Could not recover recording.') }}',
                                tabAudioUnsupported: '{{ __('This browser cannot share tab audio. Use Chrome on desktop.') }}',
                                tabAudioFailed: '{{ __('Could not capture tab audio. Please try again.') }}',
                                tabAudioNoTrack: '{{ __('No tab audio was shared. Pick a browser tab and turn on "Share tab audio".') }}',
                                consentFailed: '{{ __('Could not save your acknowledgement. Please try again.') }}',
                            },
                        })">
                            @include('meetings.partials._recording-consent')

                            <template x-if="['recording', 'paused'].includes(state)">
                                <div class="mb-2 flex items-center gap-2 text-xs text-red-600 dark:text-red-400">
                                    <div class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse flex-shrink-0"></div>
                                    {{ __('Recording continues even when switching tabs or apps.') }}
                                </div>
                            </template>
                            <div class="flex items-center gap-2">
                                <template x-if="state === 'idle' || state === 'ready'">
                                    <button @click="startRecording()"
                                            class="flex-1 inline-flex items-center justify-center gap-2 bg-red-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                                        <span x-text="transcriptChunks.length > 0 ? '{{ __('Continue Recording') }}' : '{{ __('Start Recording') }}'"></span>
                                    </button>
                                </template>
                                <template x-if="state === 'recording'">
                                    <div class="flex gap-2 w-full">
                                        <button @click="pauseRecording()"
                                                class="flex-1 inline-flex items-center justify-center gap-2 bg-yellow-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-yellow-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"/></svg>
                                            {{ __('Pause') }}
                                        </button>
                                        <button @click="stopRecording()"
                                                class="flex-1 inline-flex items-center justify-center gap-2 bg-gray-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>
                                            {{ __('Stop') }}
                                        </button>
                                    </div>
                                </template>
                                <template x-if="state === 'paused'">
                                    <div class="flex gap-2 w-full">
                                        <button @click="resumeRecording()"
                                                class="flex-1 inline-flex items-center justify-center gap-2 bg-green-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                                            {{ __('Resume') }}
                                        </button>
                                        <button @click="stopRecording()"
                                                class="flex-1 inline-flex items-center justify-center gap-2 bg-gray-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>
                                            {{ __('Stop') }}
                                        </button>
                                    </div>
                                </template>
                                <template x-if="['stopping', 'processing', 'uploading'].includes(state)">
                                    <div class="flex items-center justify-center gap-2 w-full text-gray-500 dark:text-gray-400 py-2.5">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span class="text-sm">{{ __('Processing...') }}</span>
                                    </div>
                                </template>
                            </div>

                            {{-- Meeting tab audio (Approach A): capture Meet/Zoom/Teams
                                 participants with no bot by mixing the shared tab's
                                 audio into the recording. Hidden once recording is
                                 done or errored. --}}
                            <div x-show="!['complete', 'error'].includes(state)" class="mt-2">
                                <button type="button" @click="captureTabAudio()"
                                        :class="tabAudioActive
                                            ? 'border-teal-500 bg-teal-50 text-teal-700 dark:border-teal-400 dark:bg-teal-900/20 dark:text-teal-300'
                                            : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700/40'"
                                        class="w-full inline-flex items-center justify-center gap-2 border px-3 py-2 rounded-lg text-xs font-medium transition-colors">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
                                    <span x-text="tabAudioActive ? '{{ __('Recording the online meeting') }}' : '{{ __('Record Google Meet / Zoom / Teams') }}'"></span>
                                </button>
                                <p x-show="!tabAudioActive" class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 text-center">
                                    {{ __('Share the meeting tab to capture everyone in the call.') }}
                                </p>
                                <p x-show="tabAudioActive" x-cloak class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 text-center">
                                    {{ __('Remote participants are being captured from the shared tab.') }}
                                </p>
                                <div x-show="tabAudioError" x-cloak
                                     class="mt-1 text-[11px] text-amber-600 dark:text-amber-400 text-center"
                                     x-text="tabAudioError">
                                </div>
                            </div>

                            {{-- Recording Timer --}}
                            <div x-show="state === 'recording' || state === 'paused'" x-cloak class="mt-2 text-center">
                                <span class="text-xs font-mono text-gray-500 dark:text-gray-400"
                                      x-text="'{{ __('Rec') }}: ' + formattedTimer">
                                </span>
                                <span x-show="isLongRecording" class="text-xs text-gray-400 dark:text-gray-500 ml-2"
                                      x-text="'(' + uploadedChunks + ' {{ __('chunks sent') }})'">
                                </span>
                            </div>

                            {{-- Error Message --}}
                            <div x-show="errorMessage" x-cloak
                                 class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-lg"
                                 x-text="errorMessage">
                            </div>

                            {{-- Waveform Canvas --}}
                            <canvas x-ref="waveformCanvas"
                                    x-show="state !== 'idle' && state !== 'complete' && state !== 'error'"
                                    x-cloak
                                    width="280" height="50"
                                    class="w-full mt-2 rounded-lg">
                            </canvas>
                        </div>

                        {{-- Divider --}}
                        <div class="border-t border-gray-200 dark:border-slate-700"></div>

                        {{-- End Session Button --}}
                        <button @click="endSession()"
                                :disabled="isEndingSession || sessionStatus === 'ended'"
                                class="w-full inline-flex items-center justify-center gap-2 bg-gray-900 dark:bg-white dark:text-gray-900 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!isEndingSession" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                            <svg x-show="isEndingSession" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-text="isEndingSession ? '{{ __('Ending...') }}' : '{{ __('End Session') }}'"></span>
                        </button>
                    </div>

                    {{-- Session Ended State --}}
                    <div x-show="sessionStatus === 'ended'" x-cloak class="text-center py-3">
                        <div class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span class="text-sm font-medium">{{ __('Session Ended') }}</span>
                        </div>
                        <a :href="meetingUrl"
                           class="block w-full text-center bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            {{ __('View Meeting Summary') }}
                        </a>
                    </div>
                </div>

                {{-- Attendees Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Attendees') }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400"
                              x-text="presentCount + '/' + attendees.length + ' {{ __('present') }}'">
                        </span>
                    </div>

                    {{-- Attendees List --}}
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <template x-for="attendee in attendees" :key="attendee.id">
                            <div class="flex items-center justify-between py-1.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0"
                                          :class="attendee.is_present ? 'bg-green-500' : 'bg-gray-300 dark:bg-slate-600'">
                                    </span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate"
                                          x-text="attendee.user?.name || attendee.name || '{{ __('Unknown') }}'">
                                    </span>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
                                      :class="attendee.is_present
                                          ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                                          : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400'"
                                      x-text="attendee.is_present ? '{{ __('Present') }}' : '{{ __('Absent') }}'">
                                </span>
                            </div>
                        </template>

                        {{-- Empty Attendees State --}}
                        <div x-show="attendees.length === 0" class="text-center py-3">
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('No attendees registered.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Quick Info Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-5">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">{{ __('Session Info') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Session ID') }}</dt>
                            <dd class="text-gray-900 dark:text-white font-mono">#{{ $session->id }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Started by') }}</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $session->startedBy?->name ?? 'Unknown' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Started at') }}</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $session->started_at?->format('g:i A') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Chunks') }}</dt>
                            <dd class="text-gray-900 dark:text-white" x-text="transcriptChunks.length"></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload Success Toast (floating pill, bottom-centre) --}}
    <div
        x-show="uploadToast.show"
        x-cloak
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200] w-full max-w-sm pointer-events-none"
    >
        <div
            x-show="uploadToast.show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-90"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-90"
            class="pointer-events-auto"
        >
            <div class="flex items-center gap-3 bg-gray-900/90 backdrop-blur-sm rounded-2xl shadow-2xl pl-4 pr-3 py-3">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-500/20 flex items-center justify-center">
                    <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-white leading-tight truncate" x-text="uploadToast.filename"></p>
                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                        <svg class="animate-spin h-3 w-3 text-violet-400 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ __('Transcription in progress...') }}
                    </p>
                </div>
                <button type="button"
                    @click="uploadToast.show = false; if (uploadToastTimer) clearTimeout(uploadToastTimer)"
                    class="flex-shrink-0 p-1.5 rounded-full text-gray-500 hover:text-white hover:bg-white/10 transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="mt-1.5 h-0.5 bg-white/10 rounded-full overflow-hidden mx-3">
                <div class="h-full bg-green-400 progress-bar-indeterminate rounded-full"></div>
            </div>
        </div>
    </div>
</div>
@endsection
