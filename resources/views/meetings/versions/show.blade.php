@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.versions.index', $meeting) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">Version {{ $version->version_number }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                    v{{ $version->version_number }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">{{ $meeting->title }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        {{-- Version meta --}}
        <div class="p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Change Summary</p>
                    <p class="text-sm text-gray-900">{{ $version->change_summary ?? 'Auto-saved' }}</p>
                </div>
                @if(!$isLatest)
                <form method="POST" action="{{ route('meetings.revert', $meeting) }}"
                      onsubmit="return confirm('Are you sure you want to revert this meeting to draft?')">
                    @csrf
                    <input type="hidden" name="version_id" value="{{ $version->id }}">
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Restore this version
                    </button>
                </form>
                @endif
            </div>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                @if($version->createdBy)
                    <div class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>{{ $version->createdBy->name }}</span>
                    </div>
                @endif
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $version->created_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>

        {{-- Snapshot content --}}
        <div class="p-6 space-y-4">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Title</p>
                <p class="text-base font-semibold text-gray-900">{{ $version->snapshot['title'] ?? '—' }}</p>
            </div>

            @if(!empty($version->snapshot['content']))
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Content</p>
                    <div class="prose prose-sm max-w-none text-gray-700 bg-gray-50 rounded-lg p-4">
                        {!! nl2br(e($version->snapshot['content'])) !!}
                    </div>
                </div>
            @else
                <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-400 italic">
                    No content recorded for this version.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
