<div x-data="{
    open: false,
    loading: false,
    error: null,
    suggestedAgenda: [],
    carryoverItems: [],
    discussionTopics: [],
    estimatedDuration: 60,
    selectedItems: [],

    async fetchPreparation() {
        this.loading = true;
        this.error = null;
        this.suggestedAgenda = [];
        this.carryoverItems = [];
        this.discussionTopics = [];
        this.selectedItems = [];

        try {
            const response = await fetch('{{ route('meetings.prepare-agenda.generate', $meeting) }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to generate preparation.');
            }

            const data = await response.json();
            this.suggestedAgenda = data.suggested_agenda || [];
            this.carryoverItems = data.carryover_items || [];
            this.discussionTopics = data.discussion_topics || [];
            this.estimatedDuration = data.estimated_duration_minutes || 60;
            this.selectedItems = [...this.suggestedAgenda];
        } catch (e) {
            this.error = e.message || 'An unexpected error occurred.';
        } finally {
            this.loading = false;
        }
    },

    toggleItem(item) {
        const index = this.selectedItems.indexOf(item);
        if (index > -1) {
            this.selectedItems.splice(index, 1);
        } else {
            this.selectedItems.push(item);
        }
    },

    isSelected(item) {
        return this.selectedItems.includes(item);
    },

    async applySelected() {
        if (this.selectedItems.length === 0) return;

        this.loading = true;
        try {
            const response = await fetch('{{ route('meetings.prepare-agenda.apply', $meeting) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ agenda: this.selectedItems }),
            });

            if (!response.ok) {
                throw new Error('Failed to apply agenda.');
            }

            this.open = false;

            // Show success toast
            if (window.dispatchEvent) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Agenda applied successfully!', type: 'success' }
                }));
            }

            // Reload to reflect changes
            window.location.reload();
        } catch (e) {
            this.error = e.message || 'Failed to apply agenda.';
        } finally {
            this.loading = false;
        }
    },

    openModal() {
        this.open = true;
        this.fetchPreparation();
    }
}" x-cloak>

    {{-- Trigger Button --}}
    <button @click="openModal()"
        class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        AI Prepare Agenda
    </button>

    {{-- Modal Overlay --}}
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Background overlay --}}
            <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 dark:bg-slate-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" @click="open = false">
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Modal content --}}
            <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">

                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">AI Meeting Preparation</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                    {{-- Loading State --}}
                    <div x-show="loading" class="flex flex-col items-center justify-center py-12">
                        <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Analyzing past meetings and generating agenda...</p>
                    </div>

                    {{-- Error State --}}
                    <div x-show="error && !loading" class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="ml-3 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
                        </div>
                    </div>

                    {{-- Results --}}
                    <div x-show="!loading && !error">
                        {{-- Estimated Duration --}}
                        <div class="mb-6 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Estimated Duration: <span class="font-medium text-gray-700 dark:text-gray-300" x-text="estimatedDuration + ' minutes'"></span>
                        </div>

                        {{-- Suggested Agenda --}}
                        <div x-show="suggestedAgenda.length > 0" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Suggested Agenda</h4>
                            <div class="space-y-2">
                                <template x-for="(item, index) in suggestedAgenda" :key="'agenda-' + index">
                                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                        :class="isSelected(item)
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-400'
                                            : 'border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50'">
                                        <input type="checkbox" :checked="isSelected(item)" @change="toggleItem(item)"
                                            class="mt-0.5 rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="item"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Carryover Items --}}
                        <div x-show="carryoverItems.length > 0" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Carryover Items</h4>
                            <div class="space-y-2">
                                <template x-for="(item, index) in carryoverItems" :key="'carry-' + index">
                                    <div class="flex items-center gap-3 p-3 rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20">
                                        <svg class="w-4 h-4 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        <div>
                                            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="item.title"></span>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200" x-text="item.status"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Discussion Topics --}}
                        <div x-show="discussionTopics.length > 0" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Discussion Topics</h4>
                            <div class="space-y-2">
                                <template x-for="(topic, index) in discussionTopics" :key="'topic-' + index">
                                    <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                        :class="isSelected(topic)
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-400'
                                            : 'border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50'">
                                        <input type="checkbox" :checked="isSelected(topic)" @change="toggleItem(topic)"
                                            class="mt-0.5 rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="topic"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div x-show="!loading && !error && suggestedAgenda.length > 0" class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="selectedItems.length"></span> item(s) selected
                    </span>
                    <div class="flex gap-3">
                        <button @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            Cancel
                        </button>
                        <button @click="applySelected()" :disabled="selectedItems.length === 0"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Apply Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
