@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Export Template') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Update your export template settings') }}</p>
    </div>

    <form method="POST" action="{{ route('settings.export-templates.update', $template) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Template Details') }}</h2>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $template->name) }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Description') }}</label>
                <textarea name="description" id="description" rows="2"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('description', $template->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Primary Color') }}</label>
                    <input type="text" name="primary_color" id="primary_color" value="{{ old('primary_color', $template->primary_color) }}"
                        placeholder="#003366"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
                <div>
                    <label for="font_family" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Font Family') }}</label>
                    <input type="text" name="font_family" id="font_family" value="{{ old('font_family', $template->font_family) }}"
                        placeholder="{{ __('Arial') }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                </div>
            </div>

            <div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1"
                        {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Set as default template') }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('This template will be used by default for all exports') }}</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Custom HTML & CSS') }}</h2>

            <div>
                <label for="header_html" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Header HTML') }}</label>
                <textarea name="header_html" id="header_html" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('header_html', $template->header_html) }}</textarea>
            </div>

            <div>
                <label for="footer_html" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Footer HTML') }}</label>
                <textarea name="footer_html" id="footer_html" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('footer_html', $template->footer_html) }}</textarea>
            </div>

            <div>
                <label for="css_overrides" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('CSS Overrides') }}</label>
                <textarea name="css_overrides" id="css_overrides" rows="4"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('css_overrides', $template->css_overrides) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('settings.export-templates.index') }}"
                class="px-6 py-2.5 rounded-xl text-sm font-medium border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                {{ __('Cancel') }}
            </a>
            <button type="submit"
                class="bg-violet-600 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
@endsection
