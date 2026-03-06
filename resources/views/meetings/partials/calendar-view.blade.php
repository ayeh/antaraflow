<div x-data="calendarView()" x-init="init()" class="relative">
    {{-- Calendar Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

        {{-- Month Navigation Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <button @click="prevMonth()"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="monthLabel"></h2>
            <button @click="nextMonth()"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- Day-of-week headers --}}
        <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']">
                <div class="py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider" x-text="day"></div>
            </template>
        </div>

        {{-- Calendar Grid --}}
        <div class="grid grid-cols-7 border-l border-gray-100 dark:border-gray-700">
            <template x-for="(cell, index) in calendarCells" :key="index">
                <div class="min-h-[110px] p-2 border-r border-b border-gray-100 dark:border-gray-700"
                     :class="{
                         'bg-gray-50/60 dark:bg-gray-700/20': !cell.isCurrentMonth,
                         'bg-white dark:bg-gray-800': cell.isCurrentMonth,
                     }">
                    {{-- Day number --}}
                    <div class="flex items-center justify-center w-6 h-6 mb-1 text-xs font-medium"
                         :class="{
                             'rounded-full bg-violet-600 text-white': cell.isToday,
                             'text-gray-400 dark:text-gray-500': !cell.isCurrentMonth && !cell.isToday,
                             'text-gray-600 dark:text-gray-300': cell.isCurrentMonth && !cell.isToday,
                         }"
                         x-text="cell.day">
                    </div>

                    {{-- Meeting chips --}}
                    <template x-for="meeting in cell.meetings" :key="meeting.id">
                        <button @click="openModal(meeting)"
                                class="w-full text-left text-xs px-1.5 py-0.5 rounded mb-0.5 truncate font-medium transition-opacity hover:opacity-75 focus:outline-none"
                                :class="statusClass(meeting.status)"
                                :title="meeting.title"
                                x-text="meeting.title">
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Loading overlay --}}
    <div x-show="loading" x-cloak
         class="absolute inset-0 bg-white/60 dark:bg-gray-800/60 flex items-center justify-center rounded-xl">
        <svg class="animate-spin h-6 w-6 text-violet-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    {{-- Meeting Preview Modal --}}
    <div x-show="modal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="modal.open = false">
        <div class="absolute inset-0 bg-black/50" @click="modal.open = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm p-6 z-10">
            <button @click="modal.open = false"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <template x-if="modal.meeting">
                <div>
                    <div class="text-xs font-mono text-gray-400 dark:text-gray-500 mb-1" x-text="modal.meeting.mom_number || '—'"></div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1" x-text="modal.meeting.title"></h3>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-4" x-text="modal.meeting.meeting_date"></div>

                    <div class="space-y-2 mb-5">
                        <template x-if="modal.meeting.project">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-gray-400 dark:text-gray-500 w-14 shrink-0">Project</span>
                                <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="modal.meeting.project.name"></span>
                            </div>
                        </template>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-400 dark:text-gray-500 w-14 shrink-0">Status</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="statusClass(modal.meeting.status)"
                                  x-text="modal.meeting.status.replace('_', ' ')"></span>
                        </div>
                        <template x-if="modal.meeting.start_time">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-gray-400 dark:text-gray-500 w-14 shrink-0">Time</span>
                                <span class="text-gray-700 dark:text-gray-300"
                                      x-text="modal.meeting.start_time + (modal.meeting.end_time ? ' – ' + modal.meeting.end_time : '')"></span>
                            </div>
                        </template>
                    </div>

                    <a :href="modal.meeting.url"
                       class="block w-full text-center bg-violet-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                        View Meeting
                    </a>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function calendarView() {
    return {
        year: {{ now()->year }},
        month: {{ now()->month }},
        meetings: [],
        loading: false,
        modal: { open: false, meeting: null },

        get monthLabel() {
            return new Date(this.year, this.month - 1, 1)
                .toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        get calendarCells() {
            const firstDayOfWeek = new Date(this.year, this.month - 1, 1).getDay();
            const daysInMonth = new Date(this.year, this.month, 0).getDate();
            const daysInPrevMonth = new Date(this.year, this.month - 1, 0).getDate();
            const todayStr = new Date().toISOString().slice(0, 10);
            const cells = [];

            // Trailing days from previous month
            for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                cells.push({ day: daysInPrevMonth - i, isCurrentMonth: false, isToday: false, meetings: [] });
            }

            // Current month days
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${this.year}-${String(this.month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                cells.push({
                    day: d,
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                    meetings: this.meetings.filter(m => m.meeting_date === dateStr),
                });
            }

            // Leading days of next month to fill 6 rows (42 cells)
            const remaining = 42 - cells.length;
            for (let d = 1; d <= remaining; d++) {
                cells.push({ day: d, isCurrentMonth: false, isToday: false, meetings: [] });
            }

            return cells;
        },

        async init() {
            await this.fetchMeetings();
        },

        async fetchMeetings() {
            this.loading = true;
            try {
                const res = await fetch(
                    `/meetings/calendar-data?year=${this.year}&month=${this.month}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                this.meetings = await res.json();
            } finally {
                this.loading = false;
            }
        },

        async prevMonth() {
            if (this.month === 1) { this.month = 12; this.year--; }
            else { this.month--; }
            await this.fetchMeetings();
        },

        async nextMonth() {
            if (this.month === 12) { this.month = 1; this.year++; }
            else { this.month++; }
            await this.fetchMeetings();
        },

        openModal(meeting) {
            this.modal = { open: true, meeting };
        },

        statusClass(status) {
            const map = {
                draft: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                finalized: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                approved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            };
            return map[status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
        },
    };
}
</script>
