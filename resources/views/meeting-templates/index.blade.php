@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Meeting Templates</h1>
        <a href="{{ route('meeting-templates.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Template
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($templates->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-sm text-gray-500 mb-4">No templates yet.</p>
            <a href="{{ route('meeting-templates.create') }}" class="text-sm font-medium text-violet-600 hover:text-violet-700">Create your first template</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($templates as $template)
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 hover:border-gray-300 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('meeting-templates.show', $template) }}" class="text-sm font-semibold text-gray-900 hover:text-violet-600 line-clamp-1">{{ $template->name }}</a>
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                @if($template->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">Default</span>
                                @endif
                                @if(!$template->is_shared)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Private</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($template->description)
                        <p class="text-xs text-gray-500 line-clamp-2">{{ $template->description }}</p>
                    @endif

                    @if($template->createdBy)
                        <p class="text-xs text-gray-400">by {{ $template->createdBy->name }}</p>
                    @endif

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <a href="{{ route('meeting-templates.show', $template) }}" class="text-xs font-medium text-violet-600 hover:text-violet-700">View</a>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('meeting-templates.edit', $template) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">Edit</a>
                            <form method="POST" action="{{ route('meeting-templates.destroy', $template) }}" onsubmit="return confirm('Are you sure you want to delete this template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
