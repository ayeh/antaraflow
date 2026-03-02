@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tags</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: existing tags --}}
        <div class="lg:col-span-2 space-y-3">
            @if($tags->isEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-6 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No tags yet</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tags help you organize and filter your meetings.</p>
                </div>
            @else
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 divide-y divide-gray-100 dark:divide-slate-700">
                    @foreach($tags as $tag)
                        <div x-data="{ editing: false }" class="px-5 py-4">
                            {{-- View row --}}
                            <div x-show="!editing" class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $tag->color }}"></span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $tag->name }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $tag->meetings_count }} {{ Str::plural('meeting', $tag->meetings_count) }}</span>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <button @click="editing = true" class="text-xs font-medium text-violet-600 hover:text-violet-700">Edit</button>
                                    <form method="POST" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('Delete this tag?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Inline edit form --}}
                            <div x-show="editing" x-cloak>
                                <form method="POST" action="{{ route('tags.update', $tag) }}" class="space-y-3">
                                    @csrf
                                    @method('PUT')

                                    <div class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name</label>
                                            <input type="text" name="name" value="{{ $tag->name }}" required maxlength="50"
                                                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-1.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Color</label>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            @foreach(['#A855F7', '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#06B6D4', '#6366F1'] as $preset)
                                                <label class="relative cursor-pointer">
                                                    <input type="radio" name="color" value="{{ $preset }}"
                                                        {{ $tag->color === $preset ? 'checked' : '' }}
                                                        class="sr-only peer">
                                                    <span class="block w-7 h-7 rounded-full ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-gray-600 transition-all"
                                                        style="background-color: {{ $preset }}"></span>
                                                </label>
                                            @endforeach
                                            <label class="relative cursor-pointer" title="Custom color">
                                                <input type="radio" name="color" value="{{ $tag->color }}"
                                                    {{ !in_array($tag->color, ['#A855F7','#3B82F6','#10B981','#F59E0B','#EF4444','#EC4899','#06B6D4','#6366F1']) ? 'checked' : '' }}
                                                    class="sr-only">
                                                <input type="color" name="color_custom"
                                                    value="{{ $tag->color }}"
                                                    class="w-7 h-7 rounded-full cursor-pointer border border-gray-300"
                                                    onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.checked = true;">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 pt-1">
                                        <button type="submit" class="bg-violet-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-violet-700 transition-colors">Save</button>
                                        <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right: create tag form --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Create Tag</h2>

                <form method="POST" action="{{ route('tags.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="50" placeholder="e.g. Q1 Review"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Color <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach(['#A855F7', '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#06B6D4', '#6366F1'] as $index => $preset)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="color" value="{{ $preset }}"
                                        {{ (old('color', '#A855F7') === $preset) ? 'checked' : '' }}
                                        class="sr-only peer">
                                    <span class="block w-7 h-7 rounded-full ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-gray-600 transition-all"
                                        style="background-color: {{ $preset }}"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                        Create Tag
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
