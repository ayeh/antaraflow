@props(['item', 'meeting'])

@php
    $statusOptions = collect(\App\Support\Enums\ActionItemStatus::cases())->map(fn ($s) => [
        'value' => $s->value,
        'label' => $s->label(),
        'colorClass' => $s->colorClass(),
    ])->values()->toArray();

    $updateUrl = route('meetings.action-items.status', [$meeting, $item]);
@endphp

<div
    x-data="{
        open: false,
        loading: false,
        selectedStatus: '{{ $item->status->value }}',
        comment: '',
        showComment: false,
        options: @js($statusOptions),
        get current() {
            return this.options.find(o => o.value === this.selectedStatus) || this.options[0];
        },
        close() {
            this.open = false;
            this.comment = '';
            this.showComment = false;
        },
        async save(newStatus) {
            if (newStatus === this.selectedStatus && !this.comment) { this.close(); return; }
            this.loading = true;
            const prev = this.selectedStatus;
            this.selectedStatus = newStatus;
            try {
                const res = await fetch('{{ $updateUrl }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus, comment: this.comment || null }),
                });
                if (!res.ok) throw new Error('Failed');
                const data = await res.json();
                this.selectedStatus = data.status;
                this.close();
            } catch {
                this.selectedStatus = prev;
                this.close();
                alert('Failed to update status. Please try again.');
            } finally {
                this.loading = false;
            }
        }
    }"
    class="relative inline-block"
    @keydown.escape.window="close()"
    @action-item-status-changed.window="if ($event.detail.id === {{ $item->id }}) { selectedStatus = $event.detail.status; close(); }"
>
    {{-- Badge button --}}
    <button
        type="button"
        @click="open = !open"
        :disabled="loading"
        :class="current.colorClass"
        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-opacity hover:opacity-80 disabled:opacity-50"
    >
        <span x-text="current.label"></span>
        <svg x-show="!loading" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
        <svg x-show="loading" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
    </button>

    {{-- Popover --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="close()"
        class="absolute left-0 top-full mt-1 z-50 w-64 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-lg p-2"
        style="display: none;"
    >
        {{-- Status options --}}
        <template x-for="option in options" :key="option.value">
            <button
                type="button"
                @click="save(option.value)"
                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700/50 text-left"
            >
                <span :class="option.colorClass" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap" x-text="option.label"></span>
                <svg x-show="option.value === selectedStatus" class="w-4 h-4 text-violet-500 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </button>
        </template>

        {{-- Comment section --}}
        <div class="border-t border-gray-100 dark:border-slate-700 mt-1 pt-1">
            <button
                type="button"
                @click="showComment = !showComment"
                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 px-2 py-1"
            >
                <span x-text="showComment ? 'Hide note' : 'Add a note?'"></span>
            </button>
            <div x-show="showComment" x-transition class="px-2 pb-2 space-y-1.5">
                <textarea
                    x-model="comment"
                    placeholder="Optional note..."
                    rows="2"
                    class="w-full text-xs rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-200 px-2 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-violet-500"
                ></textarea>
                <button
                    type="button"
                    @click="save(selectedStatus)"
                    :disabled="!comment.trim() || loading"
                    class="w-full text-xs font-medium px-2 py-1 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Save note
                </button>
            </div>
        </div>
    </div>
</div>
