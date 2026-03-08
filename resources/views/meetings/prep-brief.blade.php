@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    sectionsOpen: { executive_summary: true, action_items: true, metrics: true, reading_list: true, suggested_questions: true },
    markRead(section) {
        @if($brief)
        fetch('{{ route('meetings.prep-brief.section-read', [$meeting, $brief?->id ?? 0]) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ section })
        });
        @endif
    },
    toggle(section) {
        this.sectionsOpen[section] = !this.sectionsOpen[section];
        if (this.sectionsOpen[section]) {
            this.markRead(section);
        }
    }
}">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Prep Brief</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $meeting->title }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if($brief)
        {{-- Brief metadata bar --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex flex-wrap items-center gap-4 text-sm">
            @if($meeting->meeting_date)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $meeting->meeting_date->format('M j, Y') }}
                </div>
            @endif
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Est. {{ $brief->estimated_prep_minutes }} min prep
            </div>
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Generated {{ $brief->generated_at->diffForHumans() }}
            </div>
            <div class="ml-auto">
                <form method="POST" action="{{ route('meetings.prep-brief.generate', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium transition-colors">Regenerate</button>
                </form>
            </div>
        </div>

        {{-- Executive Summary --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
            <button @click="toggle('executive_summary')" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Executive Summary</h2>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': sectionsOpen.executive_summary }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="sectionsOpen.executive_summary" x-collapse class="px-6 pb-5">
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $brief->content['executive_summary'] ?? 'No summary available.' }}</p>
            </div>
        </div>

        {{-- Action Items --}}
        @php
            $actionItems = $brief->content['action_items'] ?? ['overdue' => [], 'pending' => [], 'completed' => []];
            $hasActions = count($actionItems['overdue'] ?? []) || count($actionItems['pending'] ?? []) || count($actionItems['completed'] ?? []);
        @endphp
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
            <button @click="toggle('action_items')" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your Action Items</h2>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': sectionsOpen.action_items }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="sectionsOpen.action_items" x-collapse class="px-6 pb-5 space-y-4">
                @if(!$hasActions)
                    <p class="text-sm text-gray-500 dark:text-gray-400">No action items assigned to you.</p>
                @endif

                {{-- Overdue --}}
                @if(count($actionItems['overdue'] ?? []))
                    <div>
                        <h3 class="text-sm font-medium text-red-700 dark:text-red-400 mb-2 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            Overdue ({{ count($actionItems['overdue']) }})
                        </h3>
                        <ul class="space-y-2">
                            @foreach($actionItems['overdue'] as $item)
                                <li class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/30 rounded-lg px-4 py-3">
                                    <p class="text-sm font-medium text-red-900 dark:text-red-300">{{ $item['title'] }}</p>
                                    @if(!empty($item['due_date']))
                                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">Due {{ $item['due_date'] }} &middot; {{ $item['days_overdue'] ?? 0 }} days overdue</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Pending --}}
                @if(count($actionItems['pending'] ?? []))
                    <div>
                        <h3 class="text-sm font-medium text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Pending ({{ count($actionItems['pending']) }})
                        </h3>
                        <ul class="space-y-2">
                            @foreach($actionItems['pending'] as $item)
                                <li class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/30 rounded-lg px-4 py-3">
                                    <p class="text-sm font-medium text-amber-900 dark:text-amber-300">{{ $item['title'] }}</p>
                                    @if(!empty($item['due_date']))
                                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Due {{ $item['due_date'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Completed --}}
                @if(count($actionItems['completed'] ?? []))
                    <div>
                        <h3 class="text-sm font-medium text-green-700 dark:text-green-400 mb-2 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            Recently Completed ({{ count($actionItems['completed']) }})
                        </h3>
                        <ul class="space-y-2">
                            @foreach($actionItems['completed'] as $item)
                                <li class="bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800/30 rounded-lg px-4 py-3">
                                    <p class="text-sm font-medium text-green-900 dark:text-green-300 line-through">{{ $item['title'] }}</p>
                                    @if(!empty($item['completed_at']))
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">Completed {{ $item['completed_at'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        {{-- Key Metrics --}}
        @php $metrics = $brief->content['metrics'] ?? []; @endphp
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
            <button @click="toggle('metrics')" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Key Metrics</h2>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': sectionsOpen.metrics }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="sectionsOpen.metrics" x-collapse class="px-6 pb-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ number_format(($metrics['attendance_rate'] ?? 0) * 100) }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Attendance Rate</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ number_format(($metrics['action_completion_rate'] ?? 0) * 100) }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Action Completion Rate</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ $metrics['total_meetings_6m'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Meetings (6 months)</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reading List --}}
        @php $readingList = $brief->content['reading_list'] ?? []; @endphp
        @if(count($readingList))
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <button @click="toggle('reading_list')" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Reading List</h2>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': sectionsOpen.reading_list }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="sectionsOpen.reading_list" x-collapse class="px-6 pb-5">
                    <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach($readingList as $doc)
                            <li class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $doc['filename'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $doc['estimated_pages'] }} pages &middot; ~{{ $doc['reading_time_minutes'] }} min read</p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Suggested Questions --}}
        @php
            $suggestedQuestions = $brief->content['suggested_questions'] ?? [];
        @endphp
        @if(count($suggestedQuestions))
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <button @click="toggle('suggested_questions')" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Suggested Questions</h2>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': sectionsOpen.suggested_questions }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="sectionsOpen.suggested_questions" x-collapse class="px-6 pb-5">
                    <ul class="space-y-2">
                        @foreach($suggestedQuestions as $question)
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-violet-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $question }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

    @else
        {{-- No brief state --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No prep brief yet</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Generate a personalized prep brief to help you prepare for this meeting.</p>
            <form method="POST" action="{{ route('meetings.prep-brief.generate', $meeting) }}" class="inline">
                @csrf
                <button type="submit" class="bg-violet-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Generate Prep Brief
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
