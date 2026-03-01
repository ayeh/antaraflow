@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Version History</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $meeting->title }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($versions->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-gray-500">No versions have been recorded for this meeting.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach($versions as $version)
                <div class="flex items-start gap-4 p-5">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-violet-100 text-violet-700 text-sm font-bold">
                            v{{ $version->version_number }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $version->change_summary ?? 'Auto-saved' }}
                        </p>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                            @if($version->createdBy)
                                <span>{{ $version->createdBy->name }}</span>
                                <span>&middot;</span>
                            @endif
                            <span>{{ $version->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('meetings.versions.show', [$meeting, $version]) }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                            View
                        </a>
                        @if(!$loop->first)
                            <form method="POST" action="{{ route('meetings.revert', $meeting) }}"
                                  onsubmit="return confirm('Are you sure you want to revert this meeting to draft?')">
                                @csrf
                                <input type="hidden" name="version_id" value="{{ $version->id }}">
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-violet-600 text-white hover:bg-violet-700 transition-colors">
                                    Restore
                                </button>
                            </form>
                        @else
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-400">
                                Current
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
