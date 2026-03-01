@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('organizations.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $organization->name }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('organizations.members.index', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Members</a>
            <a href="{{ route('organizations.settings.edit', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Settings</a>
            <a href="{{ route('organizations.edit', $organization) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Edit
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <div class="space-y-4">
            <div>
                <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</h3>
                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $organization->description ?: 'No description provided.' }}</p>
            </div>
            <div>
                <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</h3>
                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $organization->slug }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
