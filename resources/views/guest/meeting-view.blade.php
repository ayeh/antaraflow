<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meeting->title }} — antaraFLOW</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header Banner --}}
    <div class="bg-blue-600 text-white px-6 py-3">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-sm">antaraFLOW</span>
                <span class="text-blue-200 text-sm">&mdash; Shared Meeting View</span>
            </div>
            @if($share->expires_at)
                <span class="text-blue-100 text-xs">
                    Expires {{ $share->expires_at->format('M j, Y') }}
                </span>
            @endif
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-6 py-10 space-y-8">

        {{-- Meeting Title & Meta --}}
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $meeting->title }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                @if($meeting->meeting_date)
                    <span class="text-sm text-gray-500">
                        {{ $meeting->meeting_date->format('l, F j, Y \a\t g:i A') }}
                    </span>
                @endif
                @if($meeting->status)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @switch($meeting->status->value)
                            @case('draft') bg-gray-100 text-gray-700 @break
                            @case('in_progress') bg-yellow-100 text-yellow-700 @break
                            @case('finalized') bg-blue-100 text-blue-700 @break
                            @case('approved') bg-green-100 text-green-700 @break
                            @default bg-gray-100 text-gray-700
                        @endswitch
                    ">
                        {{ ucwords(str_replace('_', ' ', $meeting->status->value)) }}
                    </span>
                @endif
                @if($meeting->location)
                    <span class="text-sm text-gray-500">
                        <svg class="inline w-4 h-4 mr-1 -mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $meeting->location }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Attendees --}}
        @if($meeting->attendees->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Attendees</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($meeting->attendees as $attendee)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 rounded-full text-sm text-gray-700">
                            <span class="w-5 h-5 rounded-full bg-blue-200 text-blue-700 text-xs font-medium flex items-center justify-center">
                                {{ strtoupper(substr($attendee->user?->name ?? 'G', 0, 1)) }}
                            </span>
                            {{ $attendee->user?->name ?? 'Guest' }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Summary --}}
        @if($meeting->summary)
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Summary</h2>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $meeting->summary }}</p>
            </div>
        @endif

        {{-- Meeting Content --}}
        @if($meeting->content)
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Meeting Notes</h2>
                <div class="text-sm text-gray-700 leading-relaxed prose prose-sm max-w-none">
                    {!! nl2br(e($meeting->content)) !!}
                </div>
            </div>
        @endif

        {{-- Action Items --}}
        @if($meeting->actionItems->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Action Items</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($meeting->actionItems as $actionItem)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $actionItem->title }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $actionItem->assignedTo?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $actionItem->due_date ? $actionItem->due_date->format('M j, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @switch($actionItem->status?->value)
                                                @case('open') bg-gray-100 text-gray-700 @break
                                                @case('in_progress') bg-yellow-100 text-yellow-700 @break
                                                @case('completed') bg-green-100 text-green-700 @break
                                                @case('cancelled') bg-red-100 text-red-700 @break
                                                @default bg-gray-100 text-gray-700
                                            @endswitch
                                        ">
                                            {{ ucwords(str_replace('_', ' ', $actionItem->status?->value ?? 'open')) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="text-center py-6 border-t border-gray-200">
            <p class="text-sm text-gray-500">
                This is a read-only view.
                <a href="{{ route('register') }}" class="text-blue-600 hover:underline font-medium">Sign up</a>
                to create your own meeting notes.
            </p>
        </div>

    </div>
</body>
</html>
