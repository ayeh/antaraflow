<div
    x-data="{
        open: false,
        loading: false,
        saving: false,
        item: null,
        history: [],
        users: [],
        form: {},

        async openItem(meetingId, itemId) {
            this.open = true;
            this.loading = true;
            this.item = null;
            this.history = [];
            try {
                const res = await fetch(`/meetings/${meetingId}/action-items/${itemId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                });
                if (!res.ok) { throw new Error('Failed'); }
                const data = await res.json();
                this.item = data;
                this.history = data.history;
                this.users = data.users;
                this.form = {
                    title: data.title,
                    description: data.description ?? '',
                    status: data.status,
                    priority: data.priority,
                    due_date: data.due_date ?? '',
                    assigned_to: data.assigned_to ?? '',
                };
            } catch {
                this.open = false;
                alert('Failed to load item. Please try again.');
            } finally {
                this.loading = false;
            }
        },

        async save() {
            if (!this.item || this.saving) { return; }
            this.saving = true;
            const originalStatus = this.item.status;
            try {
                const res = await fetch(this.item.update_url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        ...this.form,
                        assigned_to: this.form.assigned_to || null,
                        due_date: this.form.due_date || null,
                    }),
                });
                if (!res.ok) { throw new Error('Failed'); }
                const data = await res.json();
                this.$dispatch('action-item-updated', { id: this.item.id, ...data });
                if (data.status !== originalStatus) {
                    this.$dispatch('action-item-status-changed', { id: this.item.id, status: data.status });
                }
                this.close();
            } catch {
                alert('Failed to save. Please try again.');
            } finally {
                this.saving = false;
            }
        },

        close() {
            this.open = false;
            this.item = null;
            this.history = [];
            this.form = {};
        }
    }"
    @open-slide-over.window="openItem($event.detail.meetingId, $event.detail.itemId)"
    @keydown.escape.window="if (open) { close(); }"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 bg-black/30 z-40"
        style="display: none;"
    ></div>

    {{-- Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 w-[448px] bg-white dark:bg-slate-800 shadow-xl z-50 flex flex-col overflow-hidden"
        style="display: none;"
    >
        {{-- Loading state --}}
        <div x-show="loading" class="flex items-center justify-center h-full">
            <svg class="w-8 h-8 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>

        {{-- Content (shown when loaded) --}}
        <div x-show="!loading && item" class="flex flex-col h-full overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex-shrink-0">
                <a :href="item ? item.show_url : '#'" class="text-xs text-violet-600 dark:text-violet-400 hover:underline">
                    Open full page →
                </a>
                <button
                    type="button"
                    @click="close()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                {{-- Title --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Title</label>
                    <input
                        type="text"
                        x-model="form.title"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500"
                    >
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                    <textarea
                        x-model="form.description"
                        rows="3"
                        placeholder="No description"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500 resize-none"
                    ></textarea>
                </div>

                {{-- Status + Priority --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                        <select x-model="form.status" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500">
                            @foreach(\App\Support\Enums\ActionItemStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Priority</label>
                        <select x-model="form.priority" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500">
                            @foreach(\App\Support\Enums\ActionItemPriority::cases() as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Assignee + Due Date --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Assignee</label>
                        <select x-model="form.assigned_to" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500">
                            <option value="">Unassigned</option>
                            <template x-for="u in users" :key="u.id">
                                <option :value="u.id" x-text="u.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Due Date</label>
                        <input
                            type="date"
                            x-model="form.due_date"
                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-violet-500"
                        >
                    </div>
                </div>

                {{-- Save / Cancel --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-200 dark:border-slate-700">
                    <button
                        type="button"
                        @click="close()"
                        class="text-sm px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="save()"
                        :disabled="saving"
                        class="text-sm px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-50 transition-colors"
                    >
                        <span x-text="saving ? 'Saving...' : 'Save'"></span>
                    </button>
                </div>

                {{-- Activity timeline --}}
                <div x-show="history.length > 0" class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Activity</h3>
                    <ol class="relative border-l border-gray-200 dark:border-slate-600 space-y-4 ml-2">
                        <template x-for="entry in history" :key="entry.id">
                            <li class="ml-4">
                                <div
                                    :class="entry.has_comment ? 'bg-violet-500' : 'bg-gray-300 dark:bg-slate-500'"
                                    class="absolute -left-1.5 mt-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800"
                                ></div>
                                <div class="flex flex-col gap-0.5">
                                    <p class="text-xs text-gray-700 dark:text-gray-300">
                                        <span class="font-medium" x-text="entry.changed_by_name"></span>
                                        <template x-if="entry.status_changed">
                                            <span>
                                                changed status from
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400" x-text="entry.old_label"></span>
                                                <span class="text-gray-400 mx-0.5">→</span>
                                                <span :class="entry.new_color_class" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" x-text="entry.new_label"></span>
                                            </span>
                                        </template>
                                        <template x-if="!entry.status_changed">
                                            <span x-text="(entry.field_changed === 'status' || !entry.field_changed) ? ' added a note' : ' updated ' + entry.field_changed.replace(/_/g, ' ')"></span>
                                        </template>
                                    </p>
                                    <p
                                        x-show="entry.comment"
                                        class="text-xs text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 mt-1"
                                        x-text="'\u201c' + entry.comment + '\u201d'"
                                    ></p>
                                    <time
                                        class="text-xs text-gray-400 dark:text-gray-500"
                                        x-text="entry.created_at_human + ' · ' + entry.created_at_formatted"
                                    ></time>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>

            </div>
        </div>
    </div>
</div>
