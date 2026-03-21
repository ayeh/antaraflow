@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    activeTab: '{{ isset($results) || request()->has('q') ? 'search' : 'search' }}',
    aiQuery: '',
    aiLoading: false,
    aiAnswer: '',
    aiSources: [],
    aiError: '',

    async submitAiSearch() {
        if (this.aiQuery.trim().length < 3) return;
        this.aiLoading = true;
        this.aiAnswer = '';
        this.aiSources = [];
        this.aiError = '';

        try {
            const response = await fetch('{{ route('search.ai') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ query: this.aiQuery }),
            });

            const data = await response.json();

            if (response.ok) {
                this.aiAnswer = data.answer;
                this.aiSources = data.sources ?? [];
            } else {
                this.aiError = data.message ?? 'Something went wrong. Please try again.';
            }
        } catch (e) {
            this.aiError = 'Network error. Please try again.';
        } finally {
            this.aiLoading = false;
        }
    }
}">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Search</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Search across your meetings, action items, and projects.</p>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-slate-700">
        <nav class="-mb-px flex gap-6">
            <button
                @click="activeTab = 'search'"
                :class="activeTab === 'search'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors"
            >
                Search
            </button>
            <button
                @click="activeTab = 'ai'"
                :class="activeTab === 'ai'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                </svg>
                AI Search
            </button>
        </nav>
    </div>

    {{-- Keyword Search Tab --}}
    <div x-show="activeTab === 'search'" x-cloak>
        <form method="GET" action="{{ route('search') }}" class="flex gap-3 mb-6">
            <input
                type="text"
                name="q"
                value="{{ $query ?? '' }}"
                placeholder="Search meetings, action items, projects..."
                class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                autofocus
            />
            <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                Search
            </button>
        </form>

        @if(isset($results))
            <div class="space-y-6">
                @if(!empty($results['meetings']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Meetings</h3>
                        <div class="space-y-2">
                            @foreach($results['meetings'] as $item)
                                <a href="{{ $item['url'] }}" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['mom_number'] }} · {{ $item['meeting_date'] }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">{{ $item['status'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($results['action_items']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Action Items</h3>
                        <div class="space-y-2">
                            @foreach($results['action_items'] as $item)
                                <a href="{{ $item['url'] ?? '#' }}" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['meeting_title'] }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">{{ $item['priority'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($results['projects']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Projects</h3>
                        <div class="space-y-2">
                            @foreach($results['projects'] as $item)
                                <a href="{{ $item['url'] }}" class="block p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                    @if($item['description'] ?? null)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $item['description'] }}</div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(empty($results['meetings']) && empty($results['action_items']) && empty($results['projects']))
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No results found for "{{ $query }}".</p>
                @endif
            </div>
        @endif
    </div>

    {{-- AI Search Tab --}}
    <div x-show="activeTab === 'ai'" x-cloak class="space-y-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Ask a question about any of your meetings. AI will search across all meeting records and provide an answer with sources.
            </p>
        </div>

        <div class="flex gap-3">
            <textarea
                x-model="aiQuery"
                @keydown.meta.enter="submitAiSearch()"
                @keydown.ctrl.enter="submitAiSearch()"
                placeholder="e.g. What decisions were made about the budget? Who was assigned to the marketing task?"
                rows="3"
                class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            ></textarea>
            <button
                @click="submitAiSearch()"
                :disabled="aiLoading || aiQuery.trim().length < 3"
                class="self-end bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                <template x-if="aiLoading">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="aiLoading ? 'Searching...' : 'Ask AI'"></span>
            </button>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">Press ⌘+Enter or Ctrl+Enter to submit</p>

        <template x-if="aiError">
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-sm text-red-700 dark:text-red-300" x-text="aiError"></p>
            </div>
        </template>

        <template x-if="aiAnswer">
            <div class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                        </svg>
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">AI Answer</span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap" x-text="aiAnswer"></p>
                </div>

                <template x-if="aiSources.length > 0">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Sources</h3>
                        <div class="space-y-2">
                            <template x-for="source in aiSources" :key="source.id">
                                <a :href="source.url" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="source.title"></span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500" x-text="source.meeting_date"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

</div>
@endsection
