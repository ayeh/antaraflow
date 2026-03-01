@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Organizations</h1>
        <a href="{{ route('organizations.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Organization
        </a>
    </div>

    @if($organizations->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">No organizations found.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($organizations as $organization)
                <a href="{{ route('organizations.show', $organization) }}" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 hover:border-violet-300 dark:hover:border-violet-600 transition-colors group">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">{{ $organization->name }}</h3>
                    @if($organization->description)
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ $organization->description }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
