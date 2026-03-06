@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Meeting</h1>
    </div>

    <form method="POST" action="{{ route('meetings.store') }}" class="space-y-6">
        @csrf

        {{-- Basic Information --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>

            {{-- Meeting Title --}}
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Meeting Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                    placeholder="e.g. Weekly Project Sync"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('title')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Project + Meeting Date --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project</label>
                    <select name="project_id" id="project_id"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        <option value="">— Select Project —</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }} ({{ $project->code }})</option>
                        @endforeach
                    </select>
                    @error('project_id')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="meeting_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Meeting Date <span class="text-red-500">*</span></label>
                    <input type="date" name="meeting_date" id="meeting_date" value="{{ old('meeting_date', now()->format('Y-m-d')) }}" required
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('meeting_date')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Start Time + End Time --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time</label>
                    <input type="time" name="start_time" id="start_time" value="{{ old('start_time') }}"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('start_time')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time</label>
                    <input type="time" name="end_time" id="end_time" value="{{ old('end_time') }}"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @error('end_time')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Location --}}
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location</label>
                <input type="text" name="location" id="location" value="{{ old('location') }}"
                    placeholder="e.g. Conference Room A / Zoom"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('location')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Meeting Link --}}
            <div x-data="{
                link: '{{ old('meeting_link') }}',
                get platform() {
                    if (!this.link) return null;
                    if (this.link.includes('zoom.us')) return { name: 'Zoom', color: 'blue' };
                    if (this.link.includes('meet.google.com')) return { name: 'Google Meet', color: 'green' };
                    if (this.link.includes('teams.microsoft.com') || this.link.includes('teams.live.com')) return { name: 'Microsoft Teams', color: 'violet' };
                    return { name: 'Other', color: 'gray' };
                }
            }">
                <label for="meeting_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Meeting Link</label>
                <div class="relative">
                    <input type="url" name="meeting_link" id="meeting_link" x-model="link"
                        placeholder="e.g. https://zoom.us/j/123456789"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 px-4 py-2 pr-28 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <span x-show="platform" x-cloak
                        class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                        :class="{
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': platform?.color === 'blue',
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300': platform?.color === 'green',
                            'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300': platform?.color === 'violet',
                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': platform?.color === 'gray',
                        }"
                        x-text="platform?.name">
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Platform will be auto-detected from the URL</p>
                @error('meeting_link')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Settings</h2>

            {{-- Language --}}
            <div>
                <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                <select name="language" id="language"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <option value="ms" {{ old('language', auth()->user()->currentOrganization?->language ?? 'en') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
                    <option value="en" {{ old('language', auth()->user()->currentOrganization?->language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">AI-generated content will be in this language</p>
                @error('language')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Additional Information --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Additional Information</h2>

            {{-- Prepared By --}}
            <div>
                <label for="prepared_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prepared By <span class="text-red-500">*</span></label>
                <input type="text" name="prepared_by" id="prepared_by" value="{{ old('prepared_by', auth()->user()->name) }}" required
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('prepared_by')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Share with Client --}}
            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="share_with_client" value="0">
                    <input type="checkbox" name="share_with_client" value="1"
                        {{ old('share_with_client') ? 'checked' : '' }}
                        class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Share with Client</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Allow the client to view this meeting's minutes.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('meetings.index') }}" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white rounded-lg px-6 py-2.5 text-sm font-medium transition-colors">Create MOM</button>
        </div>
    </form>
</div>
@endsection
