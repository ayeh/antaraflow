<div x-data="calendarView()" x-init="init()" class="relative">

    {{-- Two-panel layout --}}
    <div class="flex gap-6 items-start">

        {{-- ── LEFT PANEL — Activity Calendar ── --}}
        <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Activity Calendar</h2>
                <div class="flex items-center gap-2">
                    {{-- Loading indicator --}}
                    <svg x-show="loading" class="animate-spin w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" x-cloak>
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{-- Navigation --}}
                    <button @click="prevMonth()" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors text-gray-500 dark:text-gray-400" aria-label="Previous month">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button @click="nextMonth()" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors text-gray-500 dark:text-gray-400" aria-label="Next month">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Month tab strip --}}
            <div class="flex overflow-x-auto border-b border-gray-200 dark:border-slate-700 px-2 py-1 gap-0.5 scrollbar-hide">
                <template x-for="(m, idx) in months" :key="idx">
                    <button @click="goToMonth(idx + 1)"
                            :class="(idx + 1) === month
                                ? 'bg-violet-600 text-white'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700'"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors"
                            x-text="m">
                    </button>
                </template>
            </div>

            {{-- Day-of-week headers --}}
            <div class="grid grid-cols-8 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
                <div class="py-2 text-center text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">#</div>
                <template x-for="day in ['Mo','Tu','We','Th','Fr','Sa','Su']">
                    <div class="py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider" x-text="day"></div>
                </template>
            </div>

            {{-- Weekly activity rows --}}
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                <template x-for="(week, weekIdx) in weekRows" :key="weekIdx">
                    <div :id="'week-' + (weekIdx + 1)" class="grid grid-cols-8 min-h-[72px]">
                        {{-- Week number --}}
                        <div class="flex items-start justify-center pt-2 text-xs font-medium text-gray-300 dark:text-slate-600 border-r border-gray-100 dark:border-slate-700" x-text="weekIdx + 1"></div>
                        {{-- Days --}}
                        <template x-for="cell in week" :key="cell.dateStr">
                            <div class="p-1.5 border-r border-gray-50 dark:border-slate-700/50 last:border-r-0"
                                 :class="{
                                     'bg-gray-50/60 dark:bg-slate-700/20': !cell.isCurrentMonth,
                                     'bg-amber-50/30 dark:bg-amber-900/5': cell.isWeekend && cell.isCurrentMonth,
                                 }">
                                {{-- Day number --}}
                                <div class="flex items-center justify-center w-5 h-5 mb-1 text-xs font-medium"
                                     :class="{
                                         'rounded-full bg-violet-600 text-white': cell.isToday,
                                         'text-gray-300 dark:text-slate-600': !cell.isCurrentMonth && !cell.isToday,
                                         'text-gray-500 dark:text-gray-400': cell.isCurrentMonth && !cell.isToday,
                                     }"
                                     x-text="cell.day">
                                </div>
                                {{-- Meeting chips --}}
                                <template x-for="meeting in cell.meetings" :key="meeting.id">
                                    <button @click="openModal(meeting)"
                                            class="w-full text-left text-[10px] px-1.5 py-0.5 rounded mb-0.5 truncate font-medium transition-opacity hover:opacity-75 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                            :class="statusClass(meeting.status)"
                                            :title="meeting.title"
                                            x-text="meeting.title">
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Error --}}
            <div x-show="error" x-cloak class="m-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400" x-text="error"></div>
        </div>

        {{-- ── RIGHT PANEL ── --}}
        <div class="w-72 shrink-0 hidden lg:flex flex-col gap-4">

            {{-- Mini calendar --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="text-xs font-semibold text-gray-900 dark:text-white mb-3" x-text="monthLabel"></div>
                {{-- Mini grid header --}}
                <div class="grid grid-cols-7 mb-1">
                    <template x-for="d in ['M','T','W','T','F','S','S']">
                        <div class="text-center text-[10px] font-medium text-gray-400 dark:text-slate-500 py-0.5" x-text="d"></div>
                    </template>
                </div>
                {{-- Mini grid days --}}
                <div class="grid grid-cols-7 gap-y-0.5">
                    <template x-for="cell in miniCalendarCells" :key="cell.dateStr || cell.day">
                        <button @click="cell.hasMeeting && scrollToWeek(cell.weekIdx)"
                                class="relative h-6 w-6 mx-auto flex items-center justify-center text-[10px] rounded-full transition-colors"
                                :class="{
                                    'bg-violet-600 text-white font-semibold': cell.isToday,
                                    'text-gray-300 dark:text-slate-600': !cell.isCurrentMonth,
                                    'text-gray-700 dark:text-gray-300': cell.isCurrentMonth && !cell.isToday,
                                    'hover:bg-gray-100 dark:hover:bg-slate-700 cursor-pointer': cell.hasMeeting && !cell.isToday,
                                    'cursor-default': !cell.hasMeeting,
                                }"
                                :title="cell.hasMeeting ? 'Has meetings' : ''"
                                x-text="cell.day">
                            <template x-if="cell.hasMeeting && !cell.isToday">
                                <span class="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-violet-500"></span>
                            </template>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Upcoming events --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700">
                    <span class="text-xs font-semibold text-gray-900 dark:text-white">Upcoming events</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-slate-700">
                    <template x-if="upcomingMeetings.length === 0">
                        <div class="px-4 py-6 text-center text-xs text-gray-400 dark:text-slate-500">No upcoming events</div>
                    </template>
                    <template x-for="meeting in upcomingMeetings" :key="meeting.id">
                        <a :href="meeting.url"
                           class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
                           :class="upcomingBorderClass(meeting.status)">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-900 dark:text-white truncate" x-text="meeting.title"></p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5" x-text="meeting.meeting_date + (meeting.start_time ? ' · ' + meeting.start_time : '')"></p>
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                  :class="daysLeftClass(meeting.meeting_date)"
                                  x-text="daysLeftLabel(meeting.meeting_date)">
                            </span>
                        </a>
                    </template>
                </div>
            </div>

        </div>
    </div>

    {{-- Meeting Preview Modal --}}
    <div x-show="modal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="modal.open = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="modal.open = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm p-6 z-10">
            <button @click="modal.open = false"
                    class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <template x-if="modal.meeting">
                <div>
                    <div class="text-xs font-mono text-gray-400 dark:text-gray-500 mb-1" x-text="modal.meeting.mom_number || '—'"></div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1 pr-6" x-text="modal.meeting.title"></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4" x-text="modal.meeting.meeting_date"></p>
                    <div class="space-y-2 mb-5">
                        <template x-if="modal.meeting.project">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-gray-400 w-14 shrink-0 text-xs">Project</span>
                                <span class="text-gray-700 dark:text-gray-300 font-medium text-xs" x-text="modal.meeting.project.name"></span>
                            </div>
                        </template>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-400 w-14 shrink-0 text-xs">Status</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="statusClass(modal.meeting.status)"
                                  x-text="modal.meeting.status.replace('_', ' ')">
                            </span>
                        </div>
                        <template x-if="modal.meeting.start_time">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-gray-400 w-14 shrink-0">Time</span>
                                <span class="text-gray-700 dark:text-gray-300" x-text="modal.meeting.start_time + (modal.meeting.end_time ? ' – ' + modal.meeting.end_time : '')"></span>
                            </div>
                        </template>
                    </div>
                    <a :href="modal.meeting.url"
                       class="block w-full text-center bg-violet-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
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
        year:  {{ now()->year }},
        month: {{ now()->month }},
        meetings: [],
        loading: false,
        error: null,
        modal: { open: false, meeting: null },

        months: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],

        get monthLabel() {
            return new Date(this.year, this.month - 1, 1)
                .toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        // Build week rows: array of 6 weeks, each week = 7 day cells (Mo–Su)
        get weekRows() {
            const firstDay = new Date(this.year, this.month - 1, 1);
            // getDay(): 0=Sun,1=Mon...6=Sat. We want Mon-first.
            let startOffset = (firstDay.getDay() + 6) % 7; // 0=Mon, 6=Sun
            const daysInMonth = new Date(this.year, this.month, 0).getDate();
            const todayStr = new Date().toISOString().slice(0, 10);
            const cells = [];

            // Prev month fill
            const daysInPrev = new Date(this.year, this.month - 1, 0).getDate();
            for (let i = startOffset - 1; i >= 0; i--) {
                const d = daysInPrev - i;
                const m = this.month === 1 ? 12 : this.month - 1;
                const y = this.month === 1 ? this.year - 1 : this.year;
                const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                cells.push({ day: d, dateStr, isCurrentMonth: false, isToday: false, isWeekend: false, meetings: [] });
            }

            // Current month
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${this.year}-${String(this.month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const dow = (new Date(dateStr).getDay() + 6) % 7; // 0=Mon
                cells.push({
                    day: d,
                    dateStr,
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                    isWeekend: dow >= 5,
                    meetings: this.meetings.filter(m => m.meeting_date === dateStr),
                });
            }

            // Fill to complete 6 weeks
            const remaining = 42 - cells.length;
            for (let d = 1; d <= remaining; d++) {
                const m = this.month === 12 ? 1 : this.month + 1;
                const y = this.month === 12 ? this.year + 1 : this.year;
                const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                cells.push({ day: d, dateStr, isCurrentMonth: false, isToday: false, isWeekend: false, meetings: [] });
            }

            // Split into weeks of 7
            const weeks = [];
            for (let i = 0; i < cells.length; i += 7) {
                weeks.push(cells.slice(i, i + 7).map((c, j) => ({ ...c, weekIdx: Math.floor(i / 7) })));
            }
            return weeks;
        },

        // Mini calendar cells (same month, for right panel)
        get miniCalendarCells() {
            const todayStr = new Date().toISOString().slice(0, 10);
            const firstDay = new Date(this.year, this.month - 1, 1);
            let startOffset = (firstDay.getDay() + 6) % 7;
            const daysInMonth = new Date(this.year, this.month, 0).getDate();
            const daysInPrev = new Date(this.year, this.month - 1, 0).getDate();
            const cells = [];

            for (let i = startOffset - 1; i >= 0; i--) {
                cells.push({ day: daysInPrev - i, isCurrentMonth: false, isToday: false, hasMeeting: false, weekIdx: 0, dateStr: '' });
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${this.year}-${String(this.month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const weekIdx = Math.floor((cells.length) / 7);
                cells.push({
                    day: d,
                    dateStr,
                    isCurrentMonth: true,
                    isToday: dateStr === todayStr,
                    hasMeeting: this.meetings.some(m => m.meeting_date === dateStr),
                    weekIdx,
                });
            }
            while (cells.length % 7 !== 0) {
                cells.push({ day: cells.length - daysInMonth - startOffset + 1, isCurrentMonth: false, isToday: false, hasMeeting: false, weekIdx: 0, dateStr: '' });
            }
            return cells;
        },

        // Upcoming: future meetings sorted by date, max 5
        get upcomingMeetings() {
            const todayStr = new Date().toISOString().slice(0, 10);
            return this.meetings
                .filter(m => m.meeting_date >= todayStr)
                .sort((a, b) => a.meeting_date.localeCompare(b.meeting_date))
                .slice(0, 5);
        },

        async init() { await this.fetchMeetings(); },

        async fetchMeetings() {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(
                    `/meetings/calendar-data?year=${this.year}&month=${this.month}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.meetings = await res.json();
            } catch (e) {
                this.error = 'Could not load meetings. Please refresh.';
                this.meetings = [];
            } finally {
                this.loading = false;
            }
        },

        async goToMonth(m) {
            this.month = m;
            await this.fetchMeetings();
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

        scrollToWeek(weekIdx) {
            const el = document.getElementById('week-' + (weekIdx + 1));
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        openModal(meeting) { this.modal = { open: true, meeting }; },

        statusClass(status) {
            const map = {
                draft:       'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                finalized:   'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                approved:    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            };
            return map[status] ?? 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300';
        },

        upcomingBorderClass(status) {
            const map = {
                draft:       'border-l-2 border-slate-400',
                in_progress: 'border-l-2 border-blue-500',
                finalized:   'border-l-2 border-green-500',
                approved:    'border-l-2 border-amber-500',
            };
            return map[status] ?? 'border-l-2 border-gray-300';
        },

        daysLeftLabel(dateStr) {
            if (!dateStr) return '';
            const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000);
            if (diff === 0) return 'Today';
            if (diff === 1) return '1 day';
            if (diff < 0) return `${Math.abs(diff)}d ago`;
            return `${diff} days`;
        },

        daysLeftClass(dateStr) {
            if (!dateStr) return 'bg-gray-100 text-gray-500';
            const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000);
            if (diff <= 1) return 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
            if (diff <= 3) return 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400';
            return 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400';
        },
    };
}
</script>
