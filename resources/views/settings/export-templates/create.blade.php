@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">New Export Template</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Create a custom template for your exported documents</p>
    </div>

    <form method="POST" action="{{ route('settings.export-templates.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Template Details</h2>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea name="description" id="description" rows="2"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Primary Color</label>
                    <input type="text" name="primary_color" id="primary_color" value="{{ old('primary_color') }}"
                        placeholder="#003366"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
                <div>
                    <label for="font_family" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Font Family</label>
                    <input type="text" name="font_family" id="font_family" value="{{ old('font_family') }}"
                        placeholder="Arial"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
            </div>

            <div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1"
                        {{ old('is_default') ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Set as default template</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">This template will be used by default for all exports</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Custom HTML & CSS</h2>

            <div>
                <label for="header_html" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Header HTML</label>
                <textarea name="header_html" id="header_html" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('header_html') }}</textarea>
            </div>

            <div>
                <label for="footer_html" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Footer HTML</label>
                <textarea name="footer_html" id="footer_html" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('footer_html') }}</textarea>
            </div>

            <div>
                <label for="css_overrides" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSS Overrides</label>
                <textarea name="css_overrides" id="css_overrides" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('css_overrides') }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('settings.export-templates.index') }}"
                class="px-6 py-2.5 rounded-xl text-sm font-medium border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                Cancel
            </a>
            <button type="submit"
                class="bg-violet-600 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
                Create Template
            </button>
        </div>
    </form>
</div>
@endsection
