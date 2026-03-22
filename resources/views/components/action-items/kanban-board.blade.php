@props(['actionItems', 'showMeeting' => false])

@php
    $statuses = \App\Support\Enums\ActionItemStatus::cases();

    $itemsData = $actionItems->map(fn ($item) => [
        'id' => $item->id,
        'title' => $item->title,
        'status' => $item->status->value,
        'priority_label' => $item->priority->label(),
        'priority_color_class' => $item->priority->colorClass(),
        'due_date_formatted' => $item->due_date?->format('M j, Y'),
        'due_date_past' => $item->due_date?->isPast()
            && $item->status !== \App\Support\Enums\ActionItemStatus::Completed,
        'assignee_name' => $item->assignedTo?->name,
        'meeting_name' => $item->meeting?->title,
        'meeting_id' => $item->meeting?->id ?? $item->minutes_of_meeting_id,
        'status_url' => route('meetings.action-items.status', [$item->meeting, $item]),
    ])->values()->toArray();
@endphp

<div
    x-data="{
        items: @js($itemsData),
        columns: @js(collect($statuses)->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'colorClass' => $s->colorClass()])->values()->toArray()),
        showMeeting: {{ $showMeeting ? 'true' : 'false' }},
        draggingId: null,
        dragOverColumn: null,

        columnItems(statusValue) {
            return this.items.filter(i => i.status === statusValue);
        },

        onDragStart(event, itemId) {
            this.draggingId = itemId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(itemId));
            event.target.classList.add('opacity-40', 'scale-95');
        },

        onDragEnd(event) {
            this.draggingId = null;
            this.dragOverColumn = null;
            event.target.classList.remove('opacity-40', 'scale-95');
        },

        onDragOver(event, statusValue) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.dragOverColumn = statusValue;
        },

        onDragLeave(event, statusValue) {
            if (this.dragOverColumn === statusValue) {
                this.dragOverColumn = null;
            }
        },

        onDrop(event, statusValue) {
            event.preventDefault();
            this.dragOverColumn = null;
            const itemId = parseInt(event.dataTransfer.getData('text/plain'));
            if (!itemId) { return; }
            const item = this.items.find(i => i.id === itemId);
            if (item) {
                this.moveItem(itemId, statusValue, item.status_url);
            }
        },

        async moveItem(itemId, newStatus, statusUrl) {
            const item = this.items.find(i => i.id === itemId);
            if (!item || item.status === newStatus) { return; }
            const prev = item.status;
            item.status = newStatus;
            try {
                const res = await fetch(statusUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({ status: newStatus }),
                });
                if (!res.ok) { throw new Error('Failed'); }
                this.$dispatch('action-item-status-changed', { id: itemId, status: newStatus });
            } catch {
                item.status = prev;
                alert('Failed to update status. Please try again.');
            }
        }
    }"
    class="flex gap-4 overflow-x-auto pb-4"
>
    <template x-for="col in columns" :key="col.value">
        <div class="flex-shrink-0 w-72 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 flex flex-col max-h-[calc(100vh-14rem)]">
            {{-- Column header --}}
            <div class="px-4 py-3 flex items-center justify-between border-b border-gray-200 dark:border-slate-600 flex-shrink-0">
                <span :class="col.colorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="col.label"></span>
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500" x-text="columnItems(col.value).length"></span>
            </div>
            {{-- Cards drop zone --}}
            <div
                :data-status="col.value"
                class="flex-1 overflow-y-auto p-3 space-y-2 min-h-20 transition-colors duration-150"
                :class="dragOverColumn === col.value ? 'bg-violet-50 dark:bg-violet-900/20 ring-2 ring-inset ring-violet-300 dark:ring-violet-700 rounded-b-xl' : ''"
                @dragover.prevent="onDragOver($event, col.value)"
                @dragleave="onDragLeave($event, col.value)"
                @drop.prevent="onDrop($event, col.value)"
            >
                <template x-for="item in columnItems(col.value)" :key="item.id">
                    <div
                        :data-id="item.id"
                        draggable="true"
                        @dragstart="onDragStart($event, item.id)"
                        @dragend="onDragEnd($event)"
                        class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-3 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-all select-none"
                        :class="draggingId === item.id ? 'ring-2 ring-violet-400' : ''"
                    >
                        <button
                            type="button"
                            @click="$dispatch('open-slide-over', { meetingId: item.meeting_id, itemId: item.id })"
                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 text-left w-full leading-snug"
                            x-text="item.title"
                        ></button>
                        <template x-if="showMeeting && item.meeting_name">
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="item.meeting_name"></p>
                        </template>
                        <div class="flex items-center justify-between mt-2">
                            <span :class="item.priority_color_class" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" x-text="item.priority_label"></span>
                            <template x-if="item.due_date_formatted">
                                <span
                                    :class="item.due_date_past ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-400 dark:text-gray-500'"
                                    class="text-xs"
                                    x-text="item.due_date_formatted"
                                ></span>
                            </template>
                        </div>
                        <template x-if="item.assignee_name">
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1" x-text="item.assignee_name"></p>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
