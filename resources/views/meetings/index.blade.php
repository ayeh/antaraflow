@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Meetings</h1>
        <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Meeting
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <form method="GET" action="{{ route('meetings.index') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search meetings..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <select name="status" class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <option value="">All Statuses</option>
                    @foreach(\App\Support\Enums\MeetingStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Attendees</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($meetings as $meeting)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $meeting) }}" class="text-sm font-medium text-gray-900 hover:text-blue-600">{{ $meeting->title }}</a>
                                @if($meeting->createdBy)
                                    <div class="text-xs text-gray-500 mt-1">by {{ $meeting->createdBy->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $meeting->meeting_date ? $meeting->meeting_date->format('M j, Y g:i A') : 'Not set' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 text-gray-700
                                    @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 text-blue-700
                                    @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 text-yellow-700
                                    @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 text-green-700
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $meeting->attendees_count ?? $meeting->attendees()->count() }}
                            </td>
                            <td class="px-6 py-4 text-right" x-data="{ open: false }">
                                <div class="relative inline-block">
                                    <button @click="open = !open" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10">
                                        <a href="{{ route('meetings.show', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View</a>
                                        <a href="{{ route('meetings.edit', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                        <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No meetings found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($meetings->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $meetings->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
