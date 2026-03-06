@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('webhooks.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Webhook Endpoint</h1>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('webhooks.update', $webhook) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endpoint URL <span class="text-red-500">*</span></label>
                <input type="url" name="url" id="url" value="{{ old('url', $webhook->url) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('url')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <input type="text" name="description" id="description" value="{{ old('description', $webhook->description) }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Events <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    @foreach($webhookEvents as $event)
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="events[]" value="{{ $event->value }}" {{ in_array($event->value, old('events', $webhook->events ?? [])) ? 'checked' : '' }}
                                class="rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $event->label() }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $event->value }}</span>
                        </label>
                    @endforeach
                </div>
                @error('events')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $webhook->is_active) ? 'checked' : '' }}
                        class="rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
