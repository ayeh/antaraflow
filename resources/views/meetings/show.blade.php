@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $meeting->title }}</h1>
                <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
                    @if($meeting->meeting_date)
                        <span>{{ $meeting->meeting_date->format('M j, Y g:i A') }}</span>
                    @endif
                    @if($meeting->location)
                        <span>&middot; {{ $meeting->location }}</span>
                    @endif
                    @if($meeting->duration_minutes)
                        <span>&middot; {{ $meeting->duration_minutes }} min</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 text-gray-700
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 text-blue-700
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 text-yellow-700
                @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 text-green-700
                @endif">
                {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
            </span>
            {{-- Export dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 py-1 z-20">
                    <a href="{{ route('meetings.export.pdf', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">PDF</a>
                    <a href="{{ route('meetings.export.word', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">Word (.docx)</a>
                    <a href="{{ route('meetings.export.csv', $meeting) }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">CSV (Action Items)</a>
                </div>
            </div>
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress)
                <a href="{{ route('meetings.edit', $meeting) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Edit</a>
            @endif
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
                    <button type="submit" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Revert to Draft</button>
                </form>
            @endif
        </div>
    </div>

    <div x-data="{ activeTab: 'content' }">
        <div class="border-b border-gray-200 bg-white rounded-t-xl">
            <nav class="flex -mb-px overflow-x-auto">
                <button @click="activeTab = 'content'" :class="activeTab === 'content' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">Content</button>
                <button @click="activeTab = 'transcription'" :class="activeTab === 'transcription' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">Transcription</button>
                <button @click="activeTab = 'ai'" :class="activeTab === 'ai' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">AI Insights</button>
                <button @click="activeTab = 'actions'" :class="activeTab === 'actions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">Action Items</button>
                <button @click="activeTab = 'attendees'" :class="activeTab === 'attendees' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">Attendees</button>
                <button @click="activeTab = 'chat'" :class="activeTab === 'chat' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">AI Chat</button>
            </nav>
        </div>

        <div class="bg-white rounded-b-xl border-x border-b border-gray-200 p-6">
            <div x-show="activeTab === 'content'">
                @include('meetings.tabs.content', ['meeting' => $meeting])
            </div>
            <div x-show="activeTab === 'transcription'" x-cloak>
                @include('meetings.tabs.transcription', ['meeting' => $meeting])
            </div>
            <div x-show="activeTab === 'ai'" x-cloak>
                @include('meetings.tabs.ai-extraction', ['meeting' => $meeting])
            </div>
            <div x-show="activeTab === 'actions'" x-cloak>
                @include('meetings.tabs.action-items', ['meeting' => $meeting])
            </div>
            <div x-show="activeTab === 'attendees'" x-cloak>
                @include('meetings.tabs.attendees', ['meeting' => $meeting])
            </div>
            <div x-show="activeTab === 'chat'" x-cloak>
                @include('meetings.tabs.chat', ['meeting' => $meeting])
            </div>
        </div>
    </div>
</div>
@endsection
