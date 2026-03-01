@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">AI Chat &mdash; {{ $meeting->title }}</h1>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col" style="height: 70vh;">
        <div id="chat-history" class="flex-1 overflow-y-auto p-6 space-y-4">
            @forelse($history as $message)
                @if($message->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[75%]">
                            <div class="bg-violet-600 text-white px-4 py-3 rounded-2xl rounded-br-md text-sm">
                                {{ $message->message }}
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 text-right">{{ $message->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[75%]">
                            <div class="bg-gray-100 dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-4 py-3 rounded-2xl rounded-bl-md text-sm">
                                {{ $message->message }}
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $message->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No messages yet. Start a conversation about this meeting.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
