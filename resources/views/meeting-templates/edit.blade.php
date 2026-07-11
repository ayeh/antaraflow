@extends('layouts.app')

@section('content')
@php
    $existingSections = $meetingTemplate->structure['sections'] ?? [];
    $initialSections = collect(old('structure.sections', $existingSections))->values()->all();
@endphp

<div class="max-w-3xl mx-auto space-y-6" x-data="sectionBuilder({{ Js::from($initialSections) }})">
    <div class="flex items-center gap-4">
        <a href="{{ route('meeting-templates.show', $meetingTemplate) }}" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Template') }}</h1>
    </div>

    <form method="POST" action="{{ route('meeting-templates.update', $meetingTemplate) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Basic Info --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ __('Template Name') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $meetingTemplate->name) }}"
                    required
                    placeholder="e.g. Weekly Standup, Project Kickoff..."
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                >
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ __('Description') }} <span class="text-gray-400 dark:text-gray-500 font-normal">{{ __('(optional)') }}</span>
                </label>
                <textarea
                    name="description"
                    id="description"
                    rows="2"
                    placeholder="{{ __('Briefly describe when to use this template...') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none resize-none"
                >{{ old('description', $meetingTemplate->description) }}</textarea>
            </div>
        </div>

        {{-- Section Builder --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Meeting Sections') }} <span class="text-red-500">*</span></h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Add the sections that will appear in every meeting using this template.') }}</p>
            </div>

            {{-- Quick-start presets --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">{{ __('Replace with preset') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                        @click="applyPreset([
                            {title: '{{ __("Updates") }}', type: 'text'},
                            {title: '{{ __("Blockers") }}', type: 'list'},
                            {title: '{{ __("Action Items") }}', type: 'action_items'}
                        ])"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600 dark:hover:border-violet-500 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        {{ __('Daily Standup') }}
                    </button>
                    <button type="button"
                        @click="applyPreset([
                            {title: '{{ __("Agenda") }}', type: 'agenda'},
                            {title: '{{ __("Discussion") }}', type: 'text'},
                            {title: '{{ __("Decisions") }}', type: 'decisions'},
                            {title: '{{ __("Action Items") }}', type: 'action_items'}
                        ])"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600 dark:hover:border-violet-500 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        {{ __('Project Meeting') }}
                    </button>
                    <button type="button"
                        @click="applyPreset([
                            {title: '{{ __("Agenda") }}', type: 'agenda'},
                            {title: '{{ __("Notes") }}', type: 'text'},
                            {title: '{{ __("Action Items") }}', type: 'action_items'}
                        ])"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600 dark:hover:border-violet-500 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ __('General Meeting') }}
                    </button>
                    <button type="button"
                        @click="applyPreset([
                            {title: '{{ __("Objectives") }}', type: 'list'},
                            {title: '{{ __("Agenda") }}', type: 'agenda'},
                            {title: '{{ __("Team Introductions") }}', type: 'text'},
                            {title: '{{ __("Decisions") }}', type: 'decisions'},
                            {title: '{{ __("Action Items") }}', type: 'action_items'}
                        ])"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600 dark:hover:border-violet-500 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('Project Kickoff') }}
                    </button>
                </div>
            </div>

            {{-- Section list --}}
            <div class="space-y-2">
                <template x-for="(section, index) in sections" :key="index">
                    <div class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700/50 group">
                        {{-- Drag indicator (decorative) --}}
                        <div class="flex flex-col gap-0.5 shrink-0 text-gray-300 dark:text-slate-500 cursor-grab">
                            <span class="block w-3.5 h-px bg-current rounded"></span>
                            <span class="block w-3.5 h-px bg-current rounded"></span>
                            <span class="block w-3.5 h-px bg-current rounded"></span>
                        </div>

                        {{-- Section number --}}
                        <span class="shrink-0 w-5 h-5 rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 text-xs font-bold flex items-center justify-center" x-text="index + 1"></span>

                        {{-- Hidden inputs for form submission --}}
                        <input type="hidden" :name="`structure[sections][${index}][title]`" :value="section.title">
                        <input type="hidden" :name="`structure[sections][${index}][type]`" :value="section.type">

                        {{-- Title --}}
                        <input
                            type="text"
                            x-model="section.title"
                            placeholder="{{ __('Section name...') }}"
                            class="flex-1 min-w-0 rounded-md border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-1.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                        >

                        {{-- Type --}}
                        <select
                            x-model="section.type"
                            class="shrink-0 rounded-md border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-2 py-1.5 text-xs text-gray-600 dark:text-gray-300 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                        >
                            <option value="text">{{ __('Notes') }}</option>
                            <option value="agenda">{{ __('Agenda') }}</option>
                            <option value="list">{{ __('Bullet List') }}</option>
                            <option value="action_items">{{ __('Action Items') }}</option>
                            <option value="decisions">{{ __('Decisions') }}</option>
                            <option value="qa">Q&amp;A</option>
                        </select>

                        {{-- Move up/down --}}
                        <div class="flex flex-col gap-0.5 shrink-0">
                            <button type="button" @click="moveUp(index)" :disabled="index === 0"
                                class="p-0.5 rounded text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            <button type="button" @click="moveDown(index)" :disabled="index === sections.length - 1"
                                class="p-0.5 rounded text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>

                        {{-- Remove --}}
                        <button type="button" @click="removeSection(index)"
                            class="shrink-0 p-1 rounded-md text-gray-300 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                {{-- Empty state --}}
                <div x-show="sections.length === 0" class="text-center py-8 rounded-lg border-2 border-dashed border-gray-200 dark:border-slate-600">
                    <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('No sections yet. Pick a preset above or add one below.') }}</p>
                </div>
            </div>

            {{-- Add section button --}}
            <button type="button" @click="addSection()"
                class="flex items-center gap-2 w-full px-4 py-2.5 rounded-lg border border-dashed border-gray-300 dark:border-slate-600 text-sm text-gray-500 dark:text-gray-400 hover:border-violet-400 hover:text-violet-600 dark:hover:border-violet-500 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add Section') }}
            </button>
        </div>

        {{-- Settings --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('nav.settings') }}</h2>

            <label class="flex items-start gap-3 cursor-pointer group">
                <input type="hidden" name="is_shared" value="0">
                <input type="checkbox" name="is_shared" id="is_shared" value="1"
                    {{ old('is_shared', $meetingTemplate->is_shared) ? 'checked' : '' }}
                    class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ __('Share with organization') }}</span>
                    <span class="block text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ __('Everyone in your organization can use this template when creating meetings.') }}</span>
                </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer group">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" id="is_default" value="1"
                    {{ old('is_default', $meetingTemplate->is_default) ? 'checked' : '' }}
                    class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ __('Set as default template') }}</span>
                    <span class="block text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ __('This template will be automatically selected whenever someone creates a new meeting.') }}</span>
                </div>
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('meeting-templates.show', $meetingTemplate) }}" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">{{ __('Cancel') }}</a>
            <button type="submit"
                :disabled="sections.length === 0"
                class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<script>
function sectionBuilder(initialSections) {
    return {
        sections: initialSections.length > 0 ? initialSections : [],

        addSection() {
            this.sections.push({ title: '', type: 'text' });
            this.$nextTick(() => {
                const inputs = this.$el.querySelectorAll('input[placeholder="{{ __('Section name...') }}"]');
                if (inputs.length) inputs[inputs.length - 1].focus();
            });
        },

        removeSection(index) {
            this.sections.splice(index, 1);
        },

        moveUp(index) {
            if (index === 0) return;
            const arr = [...this.sections];
            [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
            this.sections = arr;
        },

        moveDown(index) {
            if (index === this.sections.length - 1) return;
            const arr = [...this.sections];
            [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
            this.sections = arr;
        },

        applyPreset(preset) {
            this.sections = preset.map(s => ({ ...s }));
        },
    };
}
</script>
@endsection
