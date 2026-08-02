@extends('layouts.app')

@php
    /**
     * Single source of truth for the initial values: these feed both the Alpine state
     * and the server-rendered `value` attributes, so the form is fully populated before
     * Alpine boots and still submits correct data if the JS bundle never runs.
     */
    $initialDate = old('meeting_date', $defaults['today']);
    $initialStart = old('start_time', $defaults['start_time']);
    $initialEnd = old('end_time', $defaults['end_time']);
    $initialLanguage = old('language', auth()->user()->currentOrganization?->language ?? 'ms');
    $initialTemplate = (string) old('meeting_template_id', $selectedTemplate?->id ?? '');
    $initialPreparedBy = old('prepared_by', auth()->user()->name);
    $initialTimeTouched = old('start_time') !== null || old('end_time') !== null;

    $timeHasError = $errors->hasAny(['start_time', 'end_time']);
    $placeHasError = $errors->hasAny(['location', 'meeting_link']);
    $advancedHasError = $errors->hasAny(['language', 'prepared_by', 'meeting_series_id']);

    $chipBase = 'group inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium cursor-pointer list-none select-none transition-colors [&::-webkit-details-marker]:hidden';
    $popBase = 'absolute left-0 top-full z-30 mt-2 w-[17rem] rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 shadow-xl space-y-3';
    $popLabel = 'block text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500';
    $miniInput = 'w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-gray-400 px-3 py-1.5 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none';
    $pillBase = 'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors cursor-pointer';
@endphp

@section('content')
<div class="max-w-2xl mx-auto pb-28 md:pb-8"
    x-data="{
        title: @js(old('title', '')),
        meetingDate: @js($initialDate),
        startTime: @js($initialStart),
        endTime: @js($initialEnd),
        timeTouched: @js($initialTimeTouched),
        mode: @js(old('meeting_link') ? 'online' : 'physical'),
        location: @js(old('location', '')),
        link: @js(old('meeting_link', '')),
        projectId: @js((string) old('project_id', '')),
        templateId: @js($initialTemplate),
        language: @js($initialLanguage),
        preparedBy: @js($initialPreparedBy),
        seriesId: @js((string) old('meeting_series_id', '')),
        submitting: false,

        dates: @js([
            'today' => $defaults['today'],
            'tomorrow' => $defaults['tomorrow'],
            'nextMonday' => $defaults['next_monday'],
        ]),
        nowTime: @js($defaults['now_time']),
        projects: @js($projects->map(fn ($p) => ['id' => (string) $p->id, 'name' => $p->name . ($p->code ? ' (' . $p->code . ')' : '')])),
        templates: @js($templates->map(fn ($t) => ['id' => (string) $t->id, 'name' => $t->name, 'description' => $t->description])),
        seriesList: @js($meetingSeries->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name])),

        get isReady() { return this.title.trim().length >= 3; },

        get dateLabel() {
            if (this.meetingDate === this.dates.today) return @js(__('Today'));
            if (this.meetingDate === this.dates.tomorrow) return @js(__('Tomorrow'));
            if (! this.meetingDate) return @js(__('Date'));
            return new Date(this.meetingDate + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        },

        get timeLabel() {
            if (! this.startTime) return @js(__('Time'));
            return this.endTime ? this.startTime + '–' + this.endTime : this.startTime;
        },

        get platform() {
            if (! this.link) return null;
            if (this.link.includes('zoom.us')) return 'Zoom';
            if (this.link.includes('meet.google.com')) return 'Google Meet';
            if (this.link.includes('teams.microsoft.com') || this.link.includes('teams.live.com')) return 'Microsoft Teams';
            return @js(__('Other'));
        },

        get placeLabel() {
            if (this.mode === 'online') return this.platform ?? @js(__('Online'));
            if (this.mode === 'hybrid') return this.location || @js(__('Hybrid'));
            return this.location || @js(__('Place'));
        },

        get placeFilled() {
            if (this.mode === 'online') return !! this.link;
            if (this.mode === 'hybrid') return !! (this.location || this.link);
            return !! this.location;
        },

        get projectLabel() {
            const p = this.projects.find(p => p.id === this.projectId);
            return p ? p.name : @js(__('Project'));
        },

        get templateLabel() {
            const t = this.templates.find(t => t.id === this.templateId);
            return t ? t.name : @js(__('Template'));
        },

        get advancedSummary() {
            const lang = this.language === 'ms' ? 'Bahasa Melayu' : 'English';
            return lang + ' · ' + (this.preparedBy || '—');
        },

        addMinutes(hhmm, mins) {
            const [h, m] = hhmm.split(':').map(Number);
            let t = (((h * 60 + m + mins) % 1440) + 1440) % 1440;
            return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
        },

        /** Any deliberate touch promotes the suggested time to a confirmed value. */
        confirmTime() { this.timeTouched = true; },

        setDuration(mins) {
            if (! this.startTime) { this.startTime = this.nowTime; }
            this.endTime = this.addMinutes(this.startTime, mins);
            this.confirmTime();
            this.flash(this.$refs.endTime);
        },

        useNow() {
            this.startTime = this.nowTime;
            this.endTime = this.addMinutes(this.nowTime, 60);
            this.confirmTime();
            this.flash(this.$refs.startTime);
            this.flash(this.$refs.endTime);
        },

        onStartChange() {
            this.confirmTime();
            if (this.startTime) {
                this.endTime = this.addMinutes(this.startTime, 60);
                this.flash(this.$refs.endTime);
            }
        },

        onLinkInput() {
            if (this.platform && this.mode === 'physical') { this.mode = 'online'; }
        },

        applyTemplate(id) {
            this.templateId = this.templateId === id ? '' : id;
        },

        flash(el) {
            if (! el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
            el.classList.remove('ring-2', 'ring-teal-400');
            void el.offsetWidth;
            el.classList.add('ring-2', 'ring-teal-400');
            setTimeout(() => el.classList.remove('ring-2', 'ring-teal-400'), 900);
        },
    }"
>
    {{-- Header + step rail: sets the expectation that this is step 1 of 5 --}}
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('meetings.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="{{ __('Back') }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Create Meeting') }}</h1>
    </div>

    @php $steps = [__('Setup'), __('Attendees'), __('Inputs'), __('Review'), __('Finalize')]; @endphp
    <div class="flex items-center pl-8 mb-6" aria-label="{{ __('Step 1 of 5') }}">
        @foreach($steps as $index => $label)
            <div class="flex items-center {{ $index < count($steps) - 1 ? 'flex-1' : '' }}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0
                    {{ $index === 0
                        ? 'bg-violet-600 text-white ring-4 ring-violet-100 dark:ring-violet-900/30'
                        : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500' }}">{{ $index + 1 }}</span>
                @if($index === 0)
                    <span class="ml-1.5 text-[11px] font-semibold text-violet-600 dark:text-violet-400 whitespace-nowrap">{{ $label }}</span>
                @endif
                @if($index < count($steps) - 1)
                    <span class="flex-1 h-0.5 mx-2 rounded bg-gray-200 dark:bg-slate-700"></span>
                @endif
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('meetings.store') }}" class="space-y-4" @submit="submitting = true">
        @csrf

        {{-- Hero title + chip rail --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 space-y-4">
            <div>
                <label for="title" class="sr-only">{{ __('Meeting Title') }}</label>
                <input type="text" name="title" id="title" x-model="title" required autofocus autocomplete="off"
                    placeholder="{{ __('What meeting are you planning?') }}"
                    class="w-full border-0 border-b-2 border-gray-200 dark:border-slate-600 bg-transparent px-0 pb-2 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white placeholder:font-medium placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:border-violet-500 focus:ring-0 outline-none transition-colors">
                @error('title')
                    <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-2">
                {{-- Chip: Date --}}
                <details class="relative" @click.outside="$el.open = false" @keydown.escape="$el.open = false" {{ $errors->has('meeting_date') ? 'open' : '' }}>
                    <summary class="{{ $chipBase }} bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span x-text="dateLabel">{{ __('Today') }}</span>
                    </summary>
                    <div class="{{ $popBase }}">
                        <span class="{{ $popLabel }}">{{ __('When') }}</span>
                        <div class="flex gap-1 rounded-lg bg-gray-50 dark:bg-slate-900/40 p-1">
                            <button type="button" class="{{ $pillBase }}" @click="meetingDate = dates.today"
                                :class="meetingDate === dates.today ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('Today') }}</button>
                            <button type="button" class="{{ $pillBase }}" @click="meetingDate = dates.tomorrow"
                                :class="meetingDate === dates.tomorrow ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('Tomorrow') }}</button>
                            <button type="button" class="{{ $pillBase }}" @click="meetingDate = dates.nextMonday"
                                :class="meetingDate === dates.nextMonday ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('Next Monday') }}</button>
                        </div>
                        <div>
                            <label for="meeting_date" class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">{{ __('Or pick a date') }}</label>
                            <input type="date" name="meeting_date" id="meeting_date" x-model="meetingDate" value="{{ $initialDate }}" required class="{{ $miniInput }}">
                        </div>
                        @error('meeting_date')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </details>

                {{-- Chip: Time --}}
                <details class="relative" @click.outside="$el.open = false" @keydown.escape="$el.open = false" {{ $timeHasError ? 'open' : '' }}>
                    <summary class="{{ $chipBase }}"
                        :class="timeTouched
                            ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300'
                            : 'border-dashed border-violet-300 dark:border-violet-800 text-gray-500 dark:text-slate-400'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        <span x-text="timeLabel">{{ $initialStart }}&ndash;{{ $initialEnd }}</span>
                        <span x-show="! timeTouched" x-cloak class="text-[10px] font-bold uppercase tracking-wide text-violet-500">{{ __('suggested') }}</span>
                    </summary>
                    <div class="{{ $popBase }}">
                        <span class="{{ $popLabel }}">{{ __('Meeting time') }}</span>
                        <div>
                            <label for="start_time" class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">{{ __('Start Time') }}</label>
                            <input type="time" name="start_time" id="start_time" x-ref="startTime" x-model="startTime" value="{{ $initialStart }}" @change="onStartChange()" class="{{ $miniInput }} transition-shadow">
                            <p x-show="! timeTouched" x-cloak class="mt-1 inline-flex items-center gap-1 rounded-full bg-teal-50 dark:bg-teal-900/20 px-2 py-0.5 text-[10px] font-bold text-teal-700 dark:text-teal-300">
                                {{ __('suggested from the current time') }}
                            </p>
                            <button type="button" @click="useNow()" class="mt-1 block text-xs font-semibold text-violet-600 dark:text-violet-400 underline underline-offset-2 hover:text-violet-700">
                                {{ __('Use the exact time now') }} (<span x-text="nowTime"></span>)
                            </button>
                            @error('start_time')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="end_time" class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">{{ __('End Time') }}</label>
                            <input type="time" name="end_time" id="end_time" x-ref="endTime" x-model="endTime" value="{{ $initialEnd }}" @change="confirmTime()" class="{{ $miniInput }} transition-shadow">
                            @error('end_time')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <span class="{{ $popLabel }} mb-1">{{ __('Quick duration') }}</span>
                            <div class="flex gap-1 rounded-lg bg-gray-50 dark:bg-slate-900/40 p-1">
                                <button type="button" class="{{ $pillBase }} text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700" @click="setDuration(30)">30 {{ __('min') }}</button>
                                <button type="button" class="{{ $pillBase }} text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700" @click="setDuration(60)">1 {{ __('hour') }}</button>
                                <button type="button" class="{{ $pillBase }} text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700" @click="setDuration(120)">2 {{ __('hours') }}</button>
                            </div>
                        </div>
                    </div>
                </details>

                {{-- Chip: Place --}}
                <details class="relative" @click.outside="$el.open = false" @keydown.escape="$el.open = false" {{ $placeHasError ? 'open' : '' }}>
                    <summary class="{{ $chipBase }}"
                        :class="placeFilled
                            ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300'
                            : 'border-gray-300 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:border-violet-400'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                        <span x-text="placeLabel">{{ __('Place') }}</span>
                    </summary>
                    <div class="{{ $popBase }}">
                        <span class="{{ $popLabel }}">{{ __('Where') }}</span>
                        <div class="flex gap-1 rounded-lg bg-gray-50 dark:bg-slate-900/40 p-1">
                            <button type="button" class="{{ $pillBase }}" @click="mode = 'physical'"
                                :class="mode === 'physical' ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('In person') }}</button>
                            <button type="button" class="{{ $pillBase }}" @click="mode = 'online'"
                                :class="mode === 'online' ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('Online') }}</button>
                            <button type="button" class="{{ $pillBase }}" @click="mode = 'hybrid'"
                                :class="mode === 'hybrid' ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">{{ __('Hybrid') }}</button>
                        </div>

                        <div x-show="mode !== 'online'">
                            <label for="location" class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">{{ __('Location') }}</label>
                            <input type="text" name="location" id="location" x-model="location" value="{{ old('location') }}"
                                placeholder="{{ __('e.g. Conference Room A') }}" class="{{ $miniInput }}">
                            @error('location')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="mode !== 'physical'">
                            <label for="meeting_link" class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">{{ __('Meeting Link') }}</label>
                            <input type="url" name="meeting_link" id="meeting_link" x-model="link" value="{{ old('meeting_link') }}" @input="onLinkInput()"
                                placeholder="{{ __('e.g. https://zoom.us/j/123456789') }}" class="{{ $miniInput }}">
                            <p x-show="platform" x-cloak class="mt-1 inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-900/30 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-300">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span x-text="platform"></span>
                            </p>
                            @error('meeting_link')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </details>

                {{-- Chip: Project --}}
                <details class="relative" @click.outside="$el.open = false" @keydown.escape="$el.open = false" {{ $errors->has('project_id') ? 'open' : '' }}>
                    <summary class="{{ $chipBase }}"
                        :class="projectId
                            ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300'
                            : 'border-gray-300 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:border-violet-400'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                        <span x-text="projectLabel">{{ __('Project') }}</span>
                    </summary>
                    <div class="{{ $popBase }}">
                        <label for="project_id" class="{{ $popLabel }}">{{ __('Link a project') }}</label>
                        <select name="project_id" id="project_id" x-model="projectId" class="{{ $miniInput }}">
                            <option value="">{{ __('— Select Project —') }}</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </details>

                {{-- Chip: Template --}}
                @if($templates->isNotEmpty())
                <details class="relative" @click.outside="$el.open = false" @keydown.escape="$el.open = false" {{ $errors->has('meeting_template_id') ? 'open' : '' }}>
                    <summary class="{{ $chipBase }}"
                        :class="templateId
                            ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300'
                            : 'border-gray-300 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:border-violet-400'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z"/></svg>
                        <span x-text="templateLabel">{{ __('Template') }}</span>
                    </summary>
                    <div class="{{ $popBase }}">
                        <div class="flex items-center justify-between">
                            <span class="{{ $popLabel }}">{{ __('Start from a template') }}</span>
                            <a href="{{ route('meeting-templates.index') }}" class="text-[11px] text-violet-600 dark:text-violet-400 hover:underline">{{ __('Manage templates') }}</a>
                        </div>
                        <input type="hidden" name="meeting_template_id" x-model="templateId" value="{{ $initialTemplate }}">
                        <div class="space-y-1.5 max-h-56 overflow-y-auto">
                            @foreach($templates as $template)
                                <button type="button" @click="applyTemplate(@js((string) $template->id))"
                                    class="w-full text-left rounded-lg border px-3 py-2 transition-colors"
                                    :class="templateId === @js((string) $template->id)
                                        ? 'border-violet-400 bg-violet-50 dark:bg-violet-900/20'
                                        : 'border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/40 hover:border-violet-300'">
                                    <span class="block text-xs font-semibold text-gray-900 dark:text-white">{{ $template->name }}{{ $template->is_default ? ' ' . __('(Default)') : '' }}</span>
                                    @if($template->description)
                                        <span class="block text-[11px] text-gray-500 dark:text-slate-400 leading-snug">{{ $template->description }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        @error('meeting_template_id')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </details>
                @endif
            </div>
        </div>

        {{-- Advanced settings: rarely changed, so collapsed behind one summary line --}}
        <details class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden group" {{ $advancedHasError ? 'open' : '' }}>
            <summary class="flex items-center gap-3 px-4 py-3 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Advanced settings') }}</span>
                    <span class="block text-xs text-gray-500 dark:text-slate-400 truncate" x-text="advancedSummary"></span>
                </span>
            </summary>

            <div class="border-t border-gray-200 dark:border-slate-700 px-4 py-4 space-y-4">
                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Language') }}</span>
                    <input type="hidden" name="language" x-model="language" value="{{ $initialLanguage }}">
                    <div class="inline-flex gap-1 rounded-lg bg-gray-50 dark:bg-slate-900/40 p-1">
                        <button type="button" class="{{ $pillBase }}" @click="language = 'ms'"
                            :class="language === 'ms' ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">Bahasa Melayu</button>
                        <button type="button" class="{{ $pillBase }}" @click="language = 'en'"
                            :class="language === 'en' ? 'bg-violet-600 text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'">English</button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('AI-generated content will be in this language') }}</p>
                    @error('language')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="prepared_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Prepared By') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="prepared_by" id="prepared_by" x-model="preparedBy" value="{{ $initialPreparedBy }}" required class="{{ $miniInput }}">
                    @error('prepared_by')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @if($meetingSeries->isNotEmpty())
                <div>
                    <label for="meeting_series_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Meeting Series') }}</label>
                    <select name="meeting_series_id" id="meeting_series_id" x-model="seriesId" class="{{ $miniInput }}">
                        <option value="">{{ __('— Not part of a series —') }}</option>
                        @foreach($meetingSeries as $series)
                            @php
                                $patternLabels = ['weekly' => __('Weekly'), 'biweekly' => __('Biweekly'), 'monthly' => __('Monthly')];
                                $label = $patternLabels[$series->recurrence_pattern] ?? ucfirst($series->recurrence_pattern);
                            @endphp
                            <option value="{{ $series->id }}" @selected(old('meeting_series_id') == $series->id)>{{ $series->name }} ({{ $label }})</option>
                        @endforeach
                    </select>
                    @error('meeting_series_id')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif
            </div>
        </details>

        {{-- Sticky action bar. Sticky rather than fixed: the sidebar is collapsible
             (md:ml-[5rem] / md:ml-[15.5rem]), so a fixed bar with a hard left offset
             would misalign whenever the user collapses it. On mobile it must clear the
             64px fixed bottom nav, which sits at z-50 and would otherwise cover it. --}}
        <div class="sticky bottom-[calc(64px_+_1rem_+_env(safe-area-inset-bottom))] md:bottom-4 z-20 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 shadow-lg backdrop-blur">
            <div class="flex items-center justify-end sm:justify-between gap-4 px-4 py-3">
                {{-- Below sm there is no room for the hint beside both buttons; the
                     disabled state of the CTA carries the same signal on its own. --}}
                <p class="hidden sm:flex items-center gap-2 text-xs transition-colors"
                    :class="isReady ? 'text-teal-600 dark:text-teal-400' : 'text-gray-400 dark:text-slate-500'">
                    <span class="w-1.5 h-1.5 rounded-full transition-colors" :class="isReady ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-600'"></span>
                    <span x-text="isReady ? @js(__('Ready — next: add attendees')) : @js(__('Add a title to begin'))"></span>
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('meetings.index') }}" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">{{ __('Cancel') }}</a>
                    <button type="submit" :disabled="! isReady || submitting"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-6 py-2.5 text-sm font-semibold transition-all duration-200 motion-safe:active:scale-95"
                        :class="isReady && ! submitting
                            ? 'bg-violet-600 hover:bg-violet-700 text-white shadow-lg shadow-violet-600/20 cursor-pointer'
                            : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500 cursor-not-allowed'">
                        <span x-text="submitting ? @js(__('Creating…')) : @js(__('Create MOM'))"></span>
                        <svg x-show="! submitting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-6l6 6-6 6"/></svg>
                        <svg x-show="submitting" x-cloak class="w-3.5 h-3.5 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
