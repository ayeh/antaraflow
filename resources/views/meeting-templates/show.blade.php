@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meeting-templates.index') }}" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $meetingTemplate->name }}</h1>
                    @if($meetingTemplate->is_default)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">Default</span>
                    @endif
                    @if(!$meetingTemplate->is_shared)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">Private</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($meetingTemplate->createdBy)
                        <span>by {{ $meetingTemplate->createdBy->name }}</span>
                    @endif
                    <span>&middot; {{ $meetingTemplate->created_at->format('M j, Y') }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('meetings.create') }}?template_id={{ $meetingTemplate->id }}" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Use this Template
            </a>
            <a href="{{ route('meeting-templates.edit', $meetingTemplate) }}" class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Edit</a>
            <form method="POST" action="{{ route('meeting-templates.destroy', $meetingTemplate) }}" onsubmit="return confirm('Are you sure you want to delete this template?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-white dark:bg-slate-800 border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Delete</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if($meetingTemplate->description)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Description</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $meetingTemplate->description }}</p>
                </div>
            @endif

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Structure</h2>
                @if(!empty($meetingTemplate->structure['sections']))
                    <ul class="space-y-2">
                        @foreach($meetingTemplate->structure['sections'] as $section)
                            <li class="flex items-center gap-3 py-2 border-b border-gray-100 dark:border-slate-700 last:border-0">
                                <span class="w-2 h-2 rounded-full bg-violet-400 flex-shrink-0"></span>
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $section['title'] ?? 'Untitled' }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">{{ $section['type'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500">No sections defined.</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Details</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Shared</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingTemplate->is_shared ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Default</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingTemplate->is_default ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingTemplate->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $meetingTemplate->updated_at->format('M j, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
