{{-- Step 4: Review (Action Items + Comments) --}}
<div
    x-data="{
        showAddForm: false,
        editingItemId: null,
        loading: false,
        successMessage: '',
        errorMessage: '',
        actionItems: @js($meeting->actionItems->load('assignedTo')->toArray()),
        orgMembers: @js($orgMembers->toArray()),

        {{-- New action item form --}}
        newItem: { title: '', description: '', assigned_to: '', due_date: '', priority: 'medium' },
        editItem: { title: '', description: '', assigned_to: '', due_date: '', priority: 'medium', status: '' },

        {{-- Comment form --}}
        commentBody: '',
        comments: @js($comments->toArray()),

        get stats() {
            const total = this.actionItems.length;
            const completed = this.actionItems.filter(i => i.status === 'completed').length;
            const inProgress = this.actionItems.filter(i => i.status === 'in_progress').length;
            const overdue = this.actionItems.filter(i =>
                i.due_date && new Date(i.due_date) < new Date() &&
                !['completed', 'cancelled'].includes(i.status)
            ).length;
            return { total, completed, inProgress, overdue };
        },

        csrfToken() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },

        priorityClasses(priority) {
            const map = {
                low: 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300',
                medium: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                high: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
                critical: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
            };
            return map[priority] || map.medium;
        },

        statusClasses(status) {
            const map = {
                open: 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300',
                in_progress: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                cancelled: 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 line-through',
                carried_forward: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300',
            };
            return map[status] || map.open;
        },

        statusLabel(status) {
            const map = {
                open: 'Open',
                in_progress: 'In Progress',
                completed: 'Completed',
                cancelled: 'Cancelled',
                carried_forward: 'Carried Forward',
            };
            return map[status] || status;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        isOverdue(item) {
            return item.due_date && new Date(item.due_date) < new Date() &&
                !['completed', 'cancelled'].includes(item.status);
        },

        resetNewItem() {
            this.newItem = { title: '', description: '', assigned_to: '', due_date: '', priority: 'medium' };
            this.showAddForm = false;
        },

        startEdit(item) {
            this.editingItemId = item.id;
            this.editItem = {
                title: item.title,
                description: item.description || '',
                assigned_to: item.assigned_to || '',
                due_date: item.due_date ? item.due_date.substring(0, 10) : '',
                priority: item.priority || 'medium',
                status: item.status || 'open',
            };
        },

        cancelEdit() {
            this.editingItemId = null;
        },

        async addActionItem() {
            if (!this.newItem.title.trim()) return;
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('meetings.action-items.store', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        title: this.newItem.title,
                        description: this.newItem.description || null,
                        assigned_to: this.newItem.assigned_to || null,
                        due_date: this.newItem.due_date || null,
                        priority: this.newItem.priority,
                    }),
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    const data = await response.json().catch(() => null);
                    const created = data?.actionItem || data?.data || {
                        id: Date.now(),
                        title: this.newItem.title,
                        description: this.newItem.description,
                        assigned_to: this.newItem.assigned_to || null,
                        due_date: this.newItem.due_date || null,
                        priority: this.newItem.priority,
                        status: 'open',
                        assigned_to_user: this.newItem.assigned_to
                            ? this.orgMembers.find(m => m.id == this.newItem.assigned_to) || null
                            : null,
                    };
                    this.actionItems.push(created);
                    this.resetNewItem();
                    this.successMessage = 'Action item added.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add action item.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Add action item failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async updateActionItem(id) {
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ url('/meetings/' . $meeting->id . '/action-items') }}/' + id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        title: this.editItem.title,
                        description: this.editItem.description || null,
                        assigned_to: this.editItem.assigned_to || null,
                        due_date: this.editItem.due_date || null,
                        priority: this.editItem.priority,
                        status: this.editItem.status,
                    }),
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    const idx = this.actionItems.findIndex(i => i.id === id);
                    if (idx !== -1) {
                        this.actionItems[idx] = {
                            ...this.actionItems[idx],
                            title: this.editItem.title,
                            description: this.editItem.description,
                            assigned_to: this.editItem.assigned_to || null,
                            due_date: this.editItem.due_date || null,
                            priority: this.editItem.priority,
                            status: this.editItem.status,
                            assigned_to_user: this.editItem.assigned_to
                                ? this.orgMembers.find(m => m.id == this.editItem.assigned_to) || null
                                : this.actionItems[idx].assigned_to_user,
                        };
                    }
                    this.editingItemId = null;
                    this.successMessage = 'Action item updated.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to update action item.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Update failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        async deleteActionItem(id) {
            if (!confirm('Delete this action item?')) return;
            this.loading = true;

            try {
                const response = await fetch('{{ url('/meetings/' . $meeting->id . '/action-items') }}/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    this.actionItems = this.actionItems.filter(i => i.id !== id);
                    this.successMessage = 'Action item removed.';
                    setTimeout(() => this.successMessage = '', 3000);
                }
            } catch (e) {
                console.error('Delete failed:', e);
            }

            this.loading = false;
        },

        async addComment() {
            if (!this.commentBody.trim()) return;
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('meetings.comments.store', $meeting) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ body: this.commentBody }),
                    redirect: 'follow',
                });

                if (response.ok || response.redirected) {
                    const data = await response.json().catch(() => null);
                    this.comments.push(data?.comment || data?.data || {
                        id: Date.now(),
                        body: this.commentBody,
                        user: { name: '{{ auth()->user()->name }}' },
                        created_at: new Date().toISOString(),
                        replies: [],
                    });
                    this.commentBody = '';
                    this.successMessage = 'Comment added.';
                    setTimeout(() => this.successMessage = '', 3000);
                } else {
                    const data = await response.json().catch(() => null);
                    this.errorMessage = data?.message || 'Failed to add comment.';
                    setTimeout(() => this.errorMessage = '', 4000);
                }
            } catch (e) {
                console.error('Comment failed:', e);
                this.errorMessage = 'Network error. Please try again.';
                setTimeout(() => this.errorMessage = '', 4000);
            }

            this.loading = false;
        },

        timeAgo(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return diffMins + 'm ago';
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 30) return diffDays + 'd ago';
            return this.formatDate(dateStr);
        },
    }"
    class="space-y-6"
>
    {{-- Flash Messages --}}
    <div x-show="successMessage" x-transition x-cloak class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-sm text-green-700 dark:text-green-300" x-text="successMessage"></p>
        </div>
    </div>

    <div x-show="errorMessage" x-transition x-cloak class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <p class="text-sm text-red-700 dark:text-red-300" x-text="errorMessage"></p>
        </div>
    </div>

    {{-- Meeting Content Preview --}}
    @php
        $summary = $meeting->extractions->firstWhere('type', 'summary');
        $decisions = $meeting->extractions->firstWhere('type', 'decisions');
        $topics = $meeting->topics->sortBy('sort_order');
        $transcriptionTexts = $meeting->transcriptions->where('status', 'completed');
        $totalDuration = $transcriptionTexts->sum('duration_seconds');
        $contentParts = [];
        if ($meeting->content) {
            $contentParts[] = $meeting->content;
        }
        foreach ($transcriptionTexts as $t) {
            if ($t->full_text) {
                $contentParts[] = $t->full_text;
            }
        }
        if ($meeting->manualNotes->isNotEmpty()) {
            foreach ($meeting->manualNotes as $note) {
                $contentParts[] = $note->content;
            }
        }
        $fullText = implode("\n\n", $contentParts);
        $wordCount = str_word_count($fullText);
    @endphp

    @if($summary || $fullText)
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6" x-data="{ expanded: false }">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                    <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Meeting Content Preview</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Review the content from your meeting inputs before generating action items</p>
                </div>
            </div>
            <button @click="expanded = !expanded" class="text-sm font-medium text-violet-600 dark:text-violet-400 hover:underline" x-text="expanded ? 'Collapse' : 'Expand'">
                Expand
            </button>
        </div>

        {{-- Metadata Tags --}}
        <div class="flex flex-wrap items-center gap-3 mb-4 text-sm text-gray-500 dark:text-gray-400">
            @if($meeting->language)
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                    {{ strtoupper($meeting->language) }}
                </span>
            @endif
            @if($totalDuration > 0)
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ sprintf('%02d:%02d:%02d', floor($totalDuration / 3600), floor(($totalDuration % 3600) / 60), $totalDuration % 60) }}
                </span>
            @endif
            @if($wordCount > 0)
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                    {{ number_format($wordCount) }} words
                </span>
            @endif
        </div>

        {{-- Full Transcript (Collapsible) --}}
        @if($fullText)
            <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 mb-4" :class="expanded ? '' : 'max-h-40 overflow-hidden relative'">
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ $fullText }}</p>
                <div x-show="!expanded" class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-gray-50 dark:from-gray-700/50 to-transparent"></div>
            </div>
        @endif

        {{-- AI Summary --}}
        @if($summary)
            <div class="bg-violet-50 dark:bg-violet-900/20 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-semibold text-violet-800 dark:text-violet-300 mb-2 flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    AI Summary
                </h4>
                <p class="text-sm text-violet-700 dark:text-violet-200 leading-relaxed">{{ $summary->content }}</p>
            </div>
        @endif

        {{-- AI Decisions --}}
        @if($decisions && !empty($decisions->structured_data))
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-emerald-800 dark:text-emerald-300 mb-2 flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Key Decisions
                </h4>
                <ul class="space-y-2">
                    @foreach($decisions->structured_data as $decision)
                        <li class="text-sm text-emerald-700 dark:text-emerald-200 flex items-start gap-2">
                            <span class="text-emerald-500 mt-0.5">•</span>
                            <div>
                                <p>{{ $decision['decision'] ?? '' }}</p>
                                @if(!empty($decision['made_by']))
                                    <p class="text-xs text-emerald-600/70 dark:text-emerald-400/70 mt-0.5">— {{ $decision['made_by'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Re-extract Button --}}
        @if($isEditable)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                <form method="POST" action="{{ route('meetings.generate', $meeting) }}" x-data="{ reExtracting: false }"
                      @submit.prevent="
                        reExtracting = true;
                        fetch('{{ route('meetings.generate', $meeting) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                        }).then(r => r.json()).then(data => {
                            if (data.success) { window.location.href = data.redirect_url; }
                            else { reExtracting = false; alert(data.message); }
                        }).catch(() => { reExtracting = false; });
                      ">
                    <button type="submit" :disabled="reExtracting"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-violet-600 to-violet-700 hover:from-violet-700 hover:to-violet-800 transition-all disabled:opacity-70">
                        <svg class="h-4 w-4" :class="reExtracting && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span x-text="reExtracting ? 'Re-extracting...' : 'Extract Action Items with AI'"></span>
                    </button>
                    <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">Clear existing items to extract again.</span>
                </form>
            </div>
        @endif
    </div>
    @endif

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total"></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">action items</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.completed"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">In Progress</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1" x-text="stats.inProgress"></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1" x-text="stats.overdue"></p>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: Action Items List (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="p-6 pb-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Action Items</h2>
                    @if($isEditable)
                        <button
                            type="button"
                            @click="showAddForm = !showAddForm"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 transition-colors"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Item
                        </button>
                    @endif
                </div>
            </div>

            <div class="px-6 pb-6">
                {{-- Empty State --}}
                <template x-if="actionItems.length === 0 && !showAddForm">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No action items</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add action items manually or run AI extraction from the Inputs step.</p>
                    </div>
                </template>

                {{-- Inline Add Form --}}
                <div x-show="showAddForm" x-cloak class="mb-4 p-4 rounded-lg border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-900/10">
                    <div class="space-y-3">
                        <div>
                            <input
                                type="text"
                                x-model="newItem.title"
                                placeholder="Action item title *"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                            />
                        </div>
                        <div>
                            <textarea
                                x-model="newItem.description"
                                rows="2"
                                placeholder="Description (optional)"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"
                            ></textarea>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Assignee</label>
                                <select x-model="newItem.assigned_to" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                    <option value="">Unassigned</option>
                                    <template x-for="member in orgMembers" :key="member.id">
                                        <option :value="member.id" x-text="member.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Due Date</label>
                                <input type="date" x-model="newItem.due_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Priority</label>
                                <select x-model="newItem.priority" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="resetNewItem()" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Cancel</button>
                            <button
                                type="button"
                                @click="addActionItem()"
                                :disabled="loading || !newItem.title.trim()"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <svg x-show="loading" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Action Item Cards --}}
                <template x-if="actionItems.length > 0">
                    <div class="space-y-2">
                        <template x-for="item in actionItems" :key="item.id">
                            <div>
                                {{-- View Mode --}}
                                <div x-show="editingItemId !== item.id" class="flex items-start justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <div class="flex items-start gap-3 min-w-0 flex-1">
                                        {{-- Status icon --}}
                                        <div class="flex-shrink-0 mt-0.5">
                                            <div class="h-5 w-5 rounded border-2 flex items-center justify-center"
                                                :class="item.status === 'completed' ? 'bg-green-500 border-green-500' : 'border-gray-300 dark:border-slate-600'">
                                                <svg x-show="item.status === 'completed'" class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white" :class="item.status === 'cancelled' ? 'line-through text-gray-400' : ''" x-text="item.title"></p>
                                            <p x-show="item.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2" x-text="item.description" x-cloak></p>
                                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                                {{-- Assignee --}}
                                                <span x-show="item.assigned_to_user" class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400" x-cloak>
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                    <span x-text="item.assigned_to_user?.name || ''"></span>
                                                </span>
                                                {{-- Due date --}}
                                                <span x-show="item.due_date" class="inline-flex items-center gap-1 text-xs" :class="isOverdue(item) ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'" x-cloak>
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    <span x-text="formatDate(item.due_date)"></span>
                                                </span>
                                                {{-- Priority badge --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium" :class="priorityClasses(item.priority)" x-text="(item.priority || 'medium').charAt(0).toUpperCase() + (item.priority || 'medium').slice(1)"></span>
                                                {{-- Status badge --}}
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium" :class="statusClasses(item.status)" x-text="statusLabel(item.status)"></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    @if($isEditable)
                                        <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                                            <button
                                                type="button"
                                                @click="startEdit(item)"
                                                class="p-1.5 text-gray-400 hover:text-violet-500 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                                                title="Edit"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="deleteActionItem(item.id)"
                                                :disabled="loading"
                                                class="p-1.5 text-gray-400 hover:text-red-500 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                                                title="Delete"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Edit Mode --}}
                                @if($isEditable)
                                    <div x-show="editingItemId === item.id" x-cloak class="p-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/10">
                                        <div class="space-y-3">
                                            <input type="text" x-model="editItem.title" placeholder="Title *" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                                            <textarea x-model="editItem.description" rows="2" placeholder="Description" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"></textarea>
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Assignee</label>
                                                    <select x-model="editItem.assigned_to" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                                        <option value="">Unassigned</option>
                                                        <template x-for="member in orgMembers" :key="member.id">
                                                            <option :value="member.id" x-text="member.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Due Date</label>
                                                    <input type="date" x-model="editItem.due_date" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Priority</label>
                                                    <select x-model="editItem.priority" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                                        <option value="low">Low</option>
                                                        <option value="medium">Medium</option>
                                                        <option value="high">High</option>
                                                        <option value="critical">Critical</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                                                    <select x-model="editItem.status" class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                                                        <option value="open">Open</option>
                                                        <option value="in_progress">In Progress</option>
                                                        <option value="completed">Completed</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="cancelEdit()" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Cancel</button>
                                                <button
                                                    type="button"
                                                    @click="updateActionItem(item.id)"
                                                    :disabled="loading || !editItem.title.trim()"
                                                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                >
                                                    <svg x-show="loading" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Save
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Right Panel: Comments (1/3 width) --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Comments Section --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    Comments & Feedback
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400" x-text="'(' + comments.length + ')'"></span>
                </h3>

                {{-- Comment List --}}
                <div class="space-y-3 mb-4 max-h-96 overflow-y-auto">
                    <template x-if="comments.length === 0">
                        <div class="text-center py-6">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No comments yet</p>
                        </div>
                    </template>

                    <template x-for="comment in comments" :key="comment.id">
                        <div class="p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="h-6 w-6 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-violet-700 dark:text-violet-300" x-text="comment.user?.name?.charAt(0) || '?'"></span>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="comment.user?.name || 'Unknown'"></span>
                                <span class="text-xs text-gray-400 dark:text-gray-500" x-text="timeAgo(comment.created_at)"></span>
                                {{-- Client Visibility Toggle --}}
                                <button
                                    type="button"
                                    @click="
                                        fetch(`/comments/${comment.id}/visibility`, {
                                            method: 'PATCH',
                                            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                                        }).then(r => r.json()).then(d => { comment.client_visible = d.client_visible })
                                    "
                                    :title="comment.client_visible ? 'Visible to clients — click to hide' : 'Internal only — click to share with clients'"
                                    :class="comment.client_visible ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'"
                                    class="ml-auto p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              :d="comment.client_visible
                                                ? 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'
                                                : 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21'" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 ml-8" x-text="comment.body"></p>

                            {{-- Replies --}}
                            <template x-if="comment.replies && comment.replies.length > 0">
                                <div class="ml-8 mt-2 space-y-2 border-l-2 border-gray-200 dark:border-slate-600 pl-3">
                                    <template x-for="reply in comment.replies" :key="reply.id">
                                        <div class="py-1">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300" x-text="reply.user?.name || 'Unknown'"></span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500" x-text="timeAgo(reply.created_at)"></span>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-400" x-text="reply.body"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Add Comment Form --}}
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <textarea
                        x-model="commentBody"
                        rows="3"
                        placeholder="Add a comment..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"
                    ></textarea>
                    <button
                        type="button"
                        @click="addComment()"
                        :disabled="loading || !commentBody.trim()"
                        class="mt-2 w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="loading ? 'Posting...' : 'Post Comment'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
