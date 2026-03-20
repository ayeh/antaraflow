{{-- Step 1: Setup - Meeting Information --}}
<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Meeting Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Meeting Date</p>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : '-' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Project</p>
                <p class="text-base font-medium text-violet-600 dark:text-violet-400">
                    {{ $meeting->project ? $meeting->project->name . ' (' . $meeting->project->code . ')' : '-' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Location</p>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ $meeting->location ?? '-' }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Prepared By</p>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ $meeting->prepared_by ?? $meeting->createdBy->name }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Language</p>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ $meeting->language === 'ms' ? 'Bahasa Melayu' : 'English' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">MOM Number</p>
                <p class="text-base font-medium text-gray-900 dark:text-white font-mono">
                    {{ $meeting->mom_number }}
                </p>
            </div>
        </div>

        @if($meeting->start_time || $meeting->end_time || $meeting->duration_minutes)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                @if($meeting->start_time)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Start Time</p>
                        <p class="text-base font-medium text-gray-900 dark:text-white">
                            {{ $meeting->start_time->format('g:i A') }}
                        </p>
                    </div>
                @endif
                @if($meeting->end_time)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">End Time</p>
                        <p class="text-base font-medium text-gray-900 dark:text-white">
                            {{ $meeting->end_time->format('g:i A') }}
                        </p>
                    </div>
                @endif
                @if($meeting->duration_minutes)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Duration</p>
                        <p class="text-base font-medium text-gray-900 dark:text-white">
                            {{ $meeting->duration_minutes }} minutes
                        </p>
                    </div>
                @endif
            </div>
        @endif

        @if($meeting->meeting_link)
            <div class="mt-4 flex items-center gap-3">
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Meeting Link</p>
                    <div class="flex items-center gap-2 mt-1">
                        @if($meeting->meeting_platform)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @if($meeting->meeting_platform === \App\Support\Enums\MeetingPlatform::Zoom) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                @elseif($meeting->meeting_platform === \App\Support\Enums\MeetingPlatform::GoogleMeet) bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                @elseif($meeting->meeting_platform === \App\Support\Enums\MeetingPlatform::MicrosoftTeams) bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300
                                @else bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300
                                @endif">
                                {{ $meeting->meeting_platform->label() }}
                            </span>
                        @endif
                        <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener noreferrer"
                            class="text-sm text-violet-600 dark:text-violet-400 hover:underline truncate max-w-md">
                            {{ $meeting->meeting_link }}
                        </a>
                    </div>
                </div>
                <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Join Meeting
                </a>
            </div>
        @endif
    </div>

    {{-- Tags --}}
    @if($meeting->tags->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Tags</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($meeting->tags as $tag)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                        {{ $tag->name }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Attendees</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $attendeeStats['total'] }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $attendeeStats['present'] }} present</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Action Items</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $actionItemStats['total'] }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $actionItemStats['completed'] }} completed</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">In Progress</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $actionItemStats['in_progress'] }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">action items</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
            <p class="text-2xl font-bold {{ $actionItemStats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }} mt-1">{{ $actionItemStats['overdue'] }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">action items</p>
        </div>
    </div>
</div>
