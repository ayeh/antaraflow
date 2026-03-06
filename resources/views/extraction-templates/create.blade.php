@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('extraction-templates.index') }}" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Extraction Template</h1>
    </div>

    <form method="POST" action="{{ route('extraction-templates.store') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-6">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                placeholder="e.g. Client Call Summary Template">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="extraction_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Extraction Type <span class="text-red-500">*</span></label>
                <select name="extraction_type" id="extraction_type" required
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    @foreach($extractionTypes as $type)
                        <option value="{{ $type->value }}" {{ old('extraction_type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="meeting_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Meeting Type <span class="text-gray-400 font-normal">(optional)</span></label>
                <select name="meeting_type" id="meeting_type"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <option value="">All Meeting Types</option>
                    @foreach($meetingTypes as $type)
                        <option value="{{ $type->value }}" {{ old('meeting_type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to apply to all meeting types.</p>
            </div>
        </div>

        <div>
            <label for="prompt_template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prompt Template <span class="text-red-500">*</span></label>
            <textarea name="prompt_template" id="prompt_template" rows="10" required
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('prompt_template') }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use <code class="bg-gray-100 dark:bg-slate-600 px-1 rounded">{transcript}</code> as a placeholder for the meeting transcript.</p>
        </div>

        <div>
            <label for="system_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">System Message <span class="text-gray-400 font-normal">(optional)</span></label>
            <textarea name="system_message" id="system_message" rows="3"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('system_message') }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional system-level instruction for the AI model.</p>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}
                class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-slate-700">
            <a href="{{ route('extraction-templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Create Template</button>
        </div>
    </form>
</div>
@endsection
