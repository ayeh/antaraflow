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
        <div class="flex flex-wrap items-center gap-3">
            {{-- Live Presence Avatars --}}
            <div x-show="viewerCount > 0" x-cloak class="flex items-center gap-1">
                <div class="flex -space-x-2">
                    <template x-for="(initials, index) in viewerInitials.slice(0, 5)" :key="index">
                        <div class="w-7 h-7 rounded-full bg-indigo-500 text-white text-xs font-medium flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
                             x-text="initials"></div>
                    </template>
                    <div x-show="viewerCount > 5" class="w-7 h-7 rounded-full bg-gray-400 text-white text-xs font-medium flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
                         x-text="'+' + (viewerCount - 5)"></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1" x-text="viewerCount + ' online'"></span>
            </div>

            {{-- Status Badge --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
                @endif"
                data-meeting-status="{{ $meeting->status->value }}">
                {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
            </span>

            {{-- Export dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-20">
                    <a href="{{ route('meetings.export.pdf', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">PDF</a>
                    <a href="{{ route('meetings.export.word', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Word (.docx)</a>
                    <a href="{{ route('meetings.export.csv', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">CSV (Action Items)</a>
                </div>
            </div>

            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress)
                <form method="POST" action="{{ route('meetings.finalize', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-600 transition-colors">Finalize</button>
                </form>
            @endif

            @if($meeting->status === \App\Support\Enums\MeetingStatus::Finalized)
                <form method="POST" action="{{ route('meetings.approve', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">Approve</button>
                </form>
            @endif

            @if($meeting->status !== \App\Support\Enums\MeetingStatus::Draft)
                <form method="POST" action="{{ route('meetings.revert', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Revert to Draft</button>
                </form>
            @endif

            <a href="{{ route('meetings.versions.index', $meeting) }}" class="inline-flex items-center gap-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </a>

            @if($meeting->extractions()->exists())
                <a href="{{ route('meetings.follow-up-email.generate', $meeting) }}" class="inline-flex items-center gap-1.5 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Follow-up Email
                </a>
            @endif

            @if(($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress) && $meeting->project_id)
                @include('meetings.partials.preparation-modal')
            @endif
        </div>
    </div>

    {{-- Stepper Bar --}}
    @include('meetings.wizard.stepper')

    {{-- Navigation Buttons --}}
    <div class="flex justify-between items-center my-4">
        <button x-show="activeStep > 1" @click="activeStep--" x-cloak
            class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span x-text="'Previous: ' + ['', 'Setup', 'Attendees', 'Inputs', 'Review'][activeStep - 1]"></span>
        </button>
        <div class="ml-auto" x-show="activeStep < 5" x-cloak>
            <button @click="activeStep++"
                class="inline-flex items-center gap-1 bg-gray-900 dark:bg-white dark:text-gray-900 text-white rounded-lg px-5 py-2 text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors">
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
