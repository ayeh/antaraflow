<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meeting->title }} — Minutes of Meeting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-slate-900 min-h-screen">

    {{-- Header --}}
    <div class="bg-violet-600 text-white px-6 py-3">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-sm">AntaraFlow</span>
                <span class="text-violet-200 text-sm">&mdash; Minutes of Meeting</span>
            </div>
            @if($access->expires_at)
                <span class="text-violet-100 text-xs">
                    Expires {{ $access->expires_at->format('M j, Y') }}
                </span>
            @endif
        </div>
    </div>

    <div class="max-w-3xl mx-auto py-8 px-4">

        {{-- Meeting Header --}}
        <div class="mb-6">
            <div class="text-xs text-gray-500 dark:text-slate-400 mb-1">
                {{ $meeting->organization->name ?? '' }}
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $meeting->title }}</h1>
            <div class="flex flex-wrap gap-4 mt-2 text-sm text-gray-500 dark:text-slate-400">
                @if($meeting->meeting_date)
                    <span>{{ $meeting->meeting_date->format('d M Y') }}</span>
                @endif
                @if($meeting->location)
                    <span>{{ $meeting->location }}</span>
                @endif
                @if($meeting->status)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        @switch($meeting->status->value)
                            @case('draft') bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 @break
                            @case('in_progress') bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 @break
                            @case('finalized') bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 @break
                            @case('approved') bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 @break
                            @default bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300
                        @endswitch
                    ">
                        {{ ucwords(str_replace('_', ' ', $meeting->status->value)) }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Attendees --}}
        @if($meeting->attendees->isNotEmpty())
            <section class="mb-6 bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3">Attendees</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($meeting->attendees as $attendee)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 dark:bg-slate-700 rounded-full text-sm text-gray-700 dark:text-slate-300">
                            <span class="w-5 h-5 rounded-full bg-violet-200 dark:bg-violet-900 text-violet-700 dark:text-violet-300 text-xs font-medium flex items-center justify-center">
                                {{ strtoupper(substr($attendee->name ?? 'G', 0, 1)) }}
                            </span>
                            {{ $attendee->name ?? 'Guest' }}
                            @if($attendee->role)
                                <span class="text-xs text-gray-400 dark:text-slate-500">({{ $attendee->role }})</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Action Items (client_visible only) --}}
        @if($meeting->actionItems->isNotEmpty())
            <section class="mb-6 bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3">Action Items</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="py-2 pr-4 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Task</th>
                                <th class="py-2 pr-4 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Assignee</th>
                                <th class="py-2 pr-4 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Due Date</th>
                                <th class="py-2 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                            @foreach($meeting->actionItems as $item)
                                <tr>
                                    <td class="py-2.5 pr-4 text-sm font-medium text-gray-800 dark:text-slate-200">{{ $item->title }}</td>
                                    <td class="py-2.5 pr-4 text-sm text-gray-500 dark:text-slate-400">
                                        {{ $item->assignedTo?->name ?? '—' }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-sm text-gray-500 dark:text-slate-400">
                                        {{ $item->due_date ? $item->due_date->format('d M Y') : '—' }}
                                    </td>
                                    <td class="py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @switch($item->status?->value)
                                                @case('open') bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 @break
                                                @case('in_progress') bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 @break
                                                @case('completed') bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 @break
                                                @case('cancelled') bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 @break
                                                @default bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300
                                            @endswitch
                                        ">
                                            {{ ucwords(str_replace('_', ' ', $item->status?->value ?? 'open')) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Notes / Comments (client_visible only) --}}
        @if($comments->isNotEmpty())
            <section class="mb-6 bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3">Notes</h2>
                <div class="space-y-3">
                    @foreach($comments as $comment)
                        <div class="text-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-700 dark:text-slate-300">{{ $comment->user->name ?? 'User' }}</span>
                                <span class="text-xs text-gray-400 dark:text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-gray-600 dark:text-slate-400 leading-relaxed">{{ $comment->body }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Footer --}}
        <div class="text-center text-xs text-gray-400 dark:text-slate-500 mt-8 pb-6">
            Powered by <span class="font-medium text-gray-500 dark:text-slate-400">AntaraFlow</span>
        </div>
    </div>
</body>
</html>
