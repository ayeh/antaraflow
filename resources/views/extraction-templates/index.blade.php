@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">AI Extraction Templates</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Customize AI prompts for different meeting types and extraction types.</p>
        </div>
        @can('create', \App\Domain\AI\Models\ExtractionTemplate::class)
        <a href="{{ route('extraction-templates.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Template
        </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($templates->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-6 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No extraction templates yet</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create custom AI prompts to tailor extractions for your meeting types.</p>
            @can('create', \App\Domain\AI\Models\ExtractionTemplate::class)
            <div class="mt-6">
                <a href="{{ route('extraction-templates.create') }}" class="inline-flex items-center rounded-md bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    New Template
                </a>
            </div>
            @endcan
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($templates as $template)
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 space-y-3 hover:border-gray-300 dark:hover:border-slate-600 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-1">{{ $template->name }}</h3>
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{{ $template->extraction_type->label() }}</span>
                                @if($template->meeting_type)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">{{ $template->meeting_type->label() }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">All Types</span>
                                @endif
                                @if(!$template->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 font-mono">{{ Str::limit($template->prompt_template, 100) }}</p>

                    @if($template->createdBy)
                        <p class="text-xs text-gray-400 dark:text-gray-500">by {{ $template->createdBy->name }}</p>
                    @endif

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-slate-700">
                        @can('update', $template)
                        <a href="{{ route('extraction-templates.edit', $template) }}" class="text-xs font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300">Edit</a>
                        @endcan
                        @can('delete', $template)
                        <form method="POST" action="{{ route('extraction-templates.destroy', $template) }}" onsubmit="return confirm('Are you sure you want to delete this template?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                        </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
