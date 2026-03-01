@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meeting-templates.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $meetingTemplate->name }}</h1>
                    @if($meetingTemplate->is_default)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">Default</span>
                    @endif
                    @if(!$meetingTemplate->is_shared)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Private</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
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
            <a href="{{ route('meeting-templates.edit', $meetingTemplate) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Edit</a>
            <form method="POST" action="{{ route('meeting-templates.destroy', $meetingTemplate) }}" onsubmit="return confirm('Are you sure you want to delete this template?')" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-white border border-red-300 text-red-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">Delete</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if($meetingTemplate->description)
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-2">Description</h2>
                    <p class="text-sm text-gray-600">{{ $meetingTemplate->description }}</p>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Structure</h2>
                @if(!empty($meetingTemplate->structure['sections']))
                    <ul class="space-y-2">
                        @foreach($meetingTemplate->structure['sections'] as $section)
                            <li class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                                <span class="w-2 h-2 rounded-full bg-violet-400 flex-shrink-0"></span>
                                <span class="text-sm font-medium text-gray-800">{{ $section['title'] ?? 'Untitled' }}</span>
                                <span class="text-xs text-gray-400 ml-auto">{{ $section['type'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400">No sections defined.</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Details</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Shared</dt>
                        <dd class="font-medium text-gray-800">{{ $meetingTemplate->is_shared ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Default</dt>
                        <dd class="font-medium text-gray-800">{{ $meetingTemplate->is_default ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Created</dt>
                        <dd class="font-medium text-gray-800">{{ $meetingTemplate->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Updated</dt>
                        <dd class="font-medium text-gray-800">{{ $meetingTemplate->updated_at->format('M j, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
