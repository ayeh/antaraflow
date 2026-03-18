{{-- Usage: @include('meetings.partials._meeting-card', ['meeting' => $meeting]) --}}
@php
    $totalActions    = $meeting->actionItems->count();
    $doneActions     = $meeting->actionItems->where('status', \App\Support\Enums\ActionItemStatus::Completed)->count();
    $allDone         = $totalActions > 0 && $doneActions === $totalActions;
    $attendees       = $meeting->attendees ?? collect();
    $visibleAttendees = $attendees->take(3);
    $extraCount      = max(0, $attendees->count() - 3);

    // Generate initials from name
    if (!function_exists('getMeetingAttendeeInitials')) {
        function getMeetingAttendeeInitials(string $name): string {
            $parts = explode(' ', trim($name));
            return strtoupper(
                count($parts) >= 2
                    ? substr($parts[0], 0, 1) . substr($parts[1], 0, 1)
                    : substr($parts[0], 0, 2)
            );
        }
    }
    $avatarColors = ['bg-violet-500','bg-blue-500','bg-teal-500','bg-amber-500','bg-rose-500','bg-indigo-500'];
@endphp

<a href="{{ route('meetings.show', $meeting) }}"
   class="group block bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 hover:shadow-md hover:border-violet-200 dark:hover:border-violet-700 transition-all focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
   aria-label="{{ $meeting->title }}">

    <div class="p-4">
        {{-- Top row: status + options --}}
        <div class="flex items-start justify-between gap-2 mb-2">
            @include('meetings.partials._status-badge', ['status' => $meeting->status])
            @if($meeting->share_with_client)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                    Shared
                </span>
            @endif
        </div>

        {{-- MOM number --}}
        @if($meeting->mom_number)
            <div class="text-xs font-mono text-gray-400 dark:text-gray-500 mb-1">{{ $meeting->mom_number }}</div>
        @endif

        {{-- Title --}}
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-violet-700 dark:group-hover:text-violet-400 transition-colors line-clamp-2 mb-2">
            {{ $meeting->title }}
        </h3>

        {{-- Date & time --}}
        @if($meeting->meeting_date)
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ $meeting->meeting_date->format('j M Y') }}
                @if($meeting->start_time)
                    · {{ $meeting->start_time->format('H:i') }}{{ $meeting->end_time ? '–'.$meeting->end_time->format('H:i') : '' }}
                @endif
            </div>
        @endif

        {{-- Location --}}
        @if($meeting->location)
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate">{{ $meeting->location }}</span>
            </div>
        @endif

        {{-- Project --}}
        @if($meeting->project)
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-3">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <span class="truncate">{{ $meeting->project->name }}</span>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="px-4 py-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between">
        {{-- Attendees --}}
        <div class="flex items-center gap-2">
            @if($visibleAttendees->count() > 0)
                <div class="flex -space-x-1.5">
                    @foreach($visibleAttendees as $i => $attendee)
                        @php
                            $name = $attendee->name ?? ($attendee->user->name ?? '?');
                            $initials = getMeetingAttendeeInitials($name);
                            $bgColor = $avatarColors[$i % count($avatarColors)];
                        @endphp
                        <div class="w-6 h-6 rounded-full {{ $bgColor }} border-2 border-white dark:border-slate-800 flex items-center justify-center text-white text-[9px] font-semibold"
                             title="{{ $name }}" aria-label="{{ $name }}">
                            {{ $initials }}
                        </div>
                    @endforeach
                    @if($extraCount > 0)
                        <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-slate-600 border-2 border-white dark:border-slate-800 flex items-center justify-center text-gray-500 dark:text-gray-400 text-[9px] font-semibold">
                            +{{ $extraCount }}
                        </div>
                    @endif
                </div>
            @elseif($meeting->createdBy)
                @php
                    $initials = getMeetingAttendeeInitials($meeting->createdBy->name);
                @endphp
                <div class="w-6 h-6 rounded-full bg-violet-500 border-2 border-white dark:border-slate-800 flex items-center justify-center text-white text-[9px] font-semibold"
                     title="{{ $meeting->createdBy->name }}" aria-label="{{ $meeting->createdBy->name }}">
                    {{ $initials }}
                </div>
            @endif
        </div>

        {{-- Action items --}}
        @if($totalActions > 0)
            <span class="text-xs font-medium {{ $allDone ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $doneActions }}/{{ $totalActions }} items
            </span>
        @else
            <span class="text-xs text-gray-400 dark:text-gray-500">No action items</span>
        @endif
    </div>
</a>
