<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Successful</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
            {{-- Success Icon --}}
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
                <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Registration Successful</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">You have been registered for the meeting.</p>

            {{-- Meeting Details Card --}}
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 mb-6 text-left">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">{{ $meeting->title }}</h2>

                <div class="space-y-2.5">
                    @if($meeting->meeting_date)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-gray-700 dark:text-gray-300">{{ $meeting->meeting_date->format('l, d F Y') }}</span>
                        </div>
                    @endif

                    @if($meeting->start_time)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ $meeting->start_time->format('g:i A') }}
                                @if($meeting->end_time) — {{ $meeting->end_time->format('g:i A') }} @endif
                            </span>
                        </div>
                    @endif

                    @if($meeting->location)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="flex-shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="text-gray-700 dark:text-gray-300">{{ $meeting->location }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Registered Person --}}
            @if(!empty($registration))
                <div class="flex items-center gap-3 p-3 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 rounded-lg mb-6">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-violet-200 dark:bg-violet-800 flex items-center justify-center">
                        <span class="text-sm font-bold text-violet-700 dark:text-violet-300">{{ strtoupper(substr($registration['name'] ?? 'G', 0, 1)) }}</span>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $registration['name'] ?? 'Guest' }}</p>
                        @if(!empty($registration['email']))
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $registration['email'] }}</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Add to Calendar --}}
            @if($meeting->meeting_date)
                @php
                    $calStart = $meeting->meeting_date->format('Ymd');
                    $calStartTime = $meeting->start_time ? $meeting->start_time->format('His') : '090000';
                    $calEndTime = $meeting->end_time ? $meeting->end_time->format('His') : '100000';
                    $calUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                        . '&text=' . urlencode($meeting->title)
                        . '&dates=' . $calStart . 'T' . $calStartTime . '/' . $calStart . 'T' . $calEndTime
                        . ($meeting->location ? '&location=' . urlencode($meeting->location) : '');
                @endphp
                <a href="{{ $calUrl }}" target="_blank" rel="noopener"
                   class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/30 transition-colors mb-4"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Add to Calendar
                </a>
            @endif

            <p class="text-xs text-gray-400 dark:text-gray-500">You can now close this page.</p>
        </div>
    </div>
</body>
</html>
