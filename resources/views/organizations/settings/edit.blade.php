@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('organizations.show', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Settings for {{ $organization->name }}</h1>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('organizations.settings.update', $organization) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="settings_theme" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Theme</label>
                <textarea id="settings_theme" name="settings[theme]" rows="6"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none resize-none">{{ $organization->settings['theme'] ?? '' }}</textarea>
                @error('settings.theme')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('organizations.show', $organization) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Cancel</a>
                <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
