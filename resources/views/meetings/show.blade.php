@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto" x-data="meetingLive({{ $meeting->id }})">
<div x-data="{
        activeStep: {{ (int) request('step', 1) }},
        isEditable: @json($isEditable),
     }">

    {{-- Meeting Title Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('meetings.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Meetings
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $meeting->title }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $meeting->mom_number }}</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Live Presence Avatars --}}
            <div x-show="viewerCount > 0" x-cloak class="flex items-center gap-1 mr-1">
                <div class="flex -space-x-2">
                    <template x-for="(initials, index) in viewerInitials.slice(0, 5)" :key="index">
                        <div class="w-7 h-7 rounded-full bg-indigo-500 text-white text-xs font-medium flex items-center justify-center ring-2 ring-white dark:ring-slate-900"
                             x-text="initials"></div>
                    </template>
                    <div x-show="viewerCount > 5" class="w-7 h-7 rounded-full bg-gray-400 text-white text-xs font-medium flex items-center justify-center ring-2 ring-white dark:ring-slate-900"
                         x-text="'+' + (viewerCount - 5)"></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1" x-text="viewerCount + ' online'"></span>
            </div>

            {{-- Status Badge --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
                @endif"
                data-meeting-status="{{ $meeting->status->value }}">
                {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
            </span>

            {{-- [•••] Overflow Dropdown --}}
            <div class="relative" x-data="{ moreOpen: false }">
                <button @click="moreOpen = !moreOpen" @keydown.escape.window="moreOpen = false"
                        title="More actions"
                        class="inline-flex items-center justify-center w-9 h-9 text-gray-600 dark:text-slate-400 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/>
                    </svg>
                </button>
                <div x-show="moreOpen" @click.outside="moreOpen = false" x-cloak
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 mt-1 w-56 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 py-1 z-30">

                    {{-- Export section --}}
                    <div class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Export</div>
                    <a href="{{ route('meetings.export.pdf', $meeting) }}" @click="moreOpen = false"
                       class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        PDF
                    </a>
                    <a href="{{ route('meetings.export.word', $meeting) }}" @click="moreOpen = false"
                       class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Word (.docx)
                    </a>
                    <a href="{{ route('meetings.export.csv', $meeting) }}" @click="moreOpen = false"
                       class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        CSV (Action Items)
                    </a>
                    <a href="{{ route('meetings.export.json', $meeting) }}" @click="moreOpen = false"
                       class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        JSON
                    </a>

                    <div class="my-1 border-t border-gray-100 dark:border-slate-700"></div>

                    {{-- Duplicate --}}
                    <form action="{{ route('meetings.duplicate', $meeting) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 text-left">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Duplicate
                        </button>
                    </form>

                    {{-- History --}}
                    <a href="{{ route('meetings.versions.index', $meeting) }}" @click="moreOpen = false"
                       class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        History
                    </a>

                    {{-- Follow-up Email (conditional) --}}
                    @if($meeting->extractions()->exists())
                        <a href="{{ route('meetings.follow-up-email.generate', $meeting) }}" @click="moreOpen = false"
                           class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                            <svg class="w-4 h-4 text-violet-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Follow-up Email
                        </a>
                    @endif

                    {{-- AI Prepare Agenda (conditional) — dispatches to preparation-modal component below --}}
                    @if(($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress) && $meeting->project_id)
                        <button @click="window.dispatchEvent(new CustomEvent('open-prep-modal')); moreOpen = false"
                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 text-left">
                            <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            AI Prepare Agenda
                        </button>
                    @endif

                    {{-- Revert to Draft (conditional — destructive, separated) --}}
                    @if($meeting->status !== \App\Support\Enums\MeetingStatus::Draft)
                        <div class="my-1 border-t border-gray-100 dark:border-slate-700"></div>
                        <form method="POST" action="{{ route('meetings.revert', $meeting) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-left">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                Revert to Draft
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Primary CTAs --}}

            {{-- Live Meeting: visible when status != Approved and user has permission --}}
            @can('startLive', $meeting)
                @if($meeting->status !== \App\Support\Enums\MeetingStatus::Approved)
                    <button
                        x-data="{ starting: false }"
                        @click="
                            starting = true;
                            fetch('{{ route('meetings.live.start', $meeting) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                }
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.session) {
                                    window.location.href = '{{ url('meetings/' . $meeting->id . '/live') }}/' + data.session.id;
                                } else {
                                    alert(data.error || 'Failed to start live session.');
                                    starting = false;
                                }
                            })
                            .catch(() => { starting = false; })
                        "
                        :disabled="starting"
                        class="bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-red-700 transition-colors inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span x-text="starting ? 'Starting...' : 'Live Meeting'"></span>
                    </button>
                @endif
            @endcan

            {{-- Finalize: Draft or InProgress --}}
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress)
                <form method="POST" action="{{ route('meetings.finalize', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-yellow-600 transition-colors">Finalize</button>
                </form>
            @endif

            {{-- Approve: Finalized only --}}
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Finalized)
                <form method="POST" action="{{ route('meetings.approve', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-green-700 transition-colors">Approve</button>
                </form>
            @endif
        </div>
    </div>

    {{-- AI Prepare Agenda modal (hidden trigger, opened via overflow dropdown dispatch) --}}
    @if(($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress) && $meeting->project_id)
        @include('meetings.partials.preparation-modal', ['inOverflow' => true])
    @endif

    {{-- Stepper Bar --}}
    @include('meetings.wizard.stepper')

    {{-- Navigation Buttons --}}
    <div class="flex justify-between items-center my-4">
        <button x-show="activeStep > 1" @click="activeStep--" x-cloak
            class="inline-flex items-center gap-1 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span x-text="'Previous: ' + ['', 'Setup', 'Attendees', 'Inputs', 'Review'][activeStep - 1]"></span>
        </button>
        <div class="ml-auto" x-show="activeStep < 5" x-cloak>
            <button @click="activeStep++"
                class="inline-flex items-center gap-1 bg-violet-600 hover:bg-violet-700 text-white rounded-xl px-5 py-2 text-sm font-medium transition-colors">
                <span x-text="'Next: ' + ['Attendees', 'Inputs', 'Review', 'Finalize', ''][activeStep - 1]"></span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- Step Content --}}
    <div x-show="activeStep === 1">
        @include('meetings.wizard.step-setup')
    </div>
    <div x-show="activeStep === 2" x-cloak>
        @include('meetings.wizard.step-attendees')
    </div>
    <div x-show="activeStep === 3" x-cloak>
        @include('meetings.wizard.step-inputs')
    </div>
    <div x-show="activeStep === 4" x-cloak>
        @include('meetings.wizard.step-review')
    </div>
    <div x-show="activeStep === 5" x-cloak>
        @include('meetings.wizard.step-finalize')
    </div>
</div>
</div>

<script>
    window.currentUserId = {{ auth()->id() }};
    window.currentUserName = @json(auth()->user()->name);
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.__offlineStore) {
            window.__offlineStore.cacheMeetingFromUrl('{{ route('meetings.offline-data', $meeting) }}');
        }
    });
</script>
@endsection
