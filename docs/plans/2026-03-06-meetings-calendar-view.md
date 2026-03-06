# Meetings Calendar View Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a monthly grid calendar view to the Meetings page as a tab toggle alongside the existing list view, with meeting chips and a modal preview on click.

**Architecture:** A new `GET /meetings/calendar-data` JSON endpoint returns meetings for a given year/month. The existing `meetings/index.blade.php` gets a List/Calendar tab toggle; when Calendar is active it renders a new Alpine.js partial that fetches and displays the grid. No new npm dependencies.

**Tech Stack:** Laravel 12, Alpine.js (already installed), Tailwind CSS

---

### Task 1: Add `calendarData()` endpoint

**Files:**
- Modify: `app/Domain/Meeting/Controllers/MeetingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Meeting/Controllers/MeetingControllerTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Domain/Meeting/Controllers/MeetingControllerTest.php`:

```php
test('user can fetch calendar data for a month', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'March Meeting',
        'meeting_date' => '2026-03-15 09:00:00',
        'status' => MeetingStatus::Finalized,
    ]);

    // Meeting outside the month should not appear
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'April Meeting',
        'meeting_date' => '2026-04-01 09:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.calendar-data', ['year' => 2026, 'month' => 3]));

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.title', 'March Meeting');
    $response->assertJsonPath('0.meeting_date', '2026-03-15');
    $response->assertJsonPath('0.status', 'finalized');
    $response->assertJsonStructure(['*' => ['id', 'title', 'mom_number', 'meeting_date', 'start_time', 'end_time', 'status', 'project', 'url']]);
});

test('calendar data defaults to current month', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->startOfMonth()->addDays(2),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('meetings.calendar-data'));

    $response->assertOk();
    $response->assertJsonCount(1);
});

test('unauthenticated user cannot fetch calendar data', function () {
    $this->getJson(route('meetings.calendar-data'))->assertRedirect();
});
```

**Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter="calendar data"
```
Expected: FAIL — route/method not found.

**Step 3: Add route**

In `routes/web.php`, after the meetings resource block (line ~112):

```php
Route::get('meetings/calendar-data', [MeetingController::class, 'calendarData'])->name('meetings.calendar-data');
```

> Place this BEFORE `Route::resource('meetings', MeetingController::class)` to avoid `{meeting}` catching `calendar-data`.

**Step 4: Add `calendarData()` method to `MeetingController`**

Add after the `index()` method in `app/Domain/Meeting/Controllers/MeetingController.php`:

```php
public function calendarData(Request $request): \Illuminate\Http\JsonResponse
{
    $this->authorize('viewAny', MinutesOfMeeting::class);

    $year = (int) $request->get('year', now()->year);
    $month = (int) $request->get('month', now()->month);

    $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
    $end = $start->copy()->endOfMonth();

    $meetings = MinutesOfMeeting::with('project')
        ->whereBetween('meeting_date', [$start, $end])
        ->orderBy('meeting_date')
        ->get(['id', 'title', 'mom_number', 'meeting_date', 'start_time', 'end_time', 'status', 'project_id']);

    return response()->json($meetings->map(fn (MinutesOfMeeting $meeting): array => [
        'id' => $meeting->id,
        'mom_number' => $meeting->mom_number,
        'title' => $meeting->title,
        'meeting_date' => $meeting->meeting_date?->format('Y-m-d'),
        'start_time' => $meeting->start_time?->format('H:i'),
        'end_time' => $meeting->end_time?->format('H:i'),
        'status' => $meeting->status->value,
        'project' => $meeting->project
            ? ['name' => $meeting->project->name, 'code' => $meeting->project->code]
            : null,
        'url' => route('meetings.show', $meeting->id),
    ]));
}
```

**Step 5: Run tests to confirm they pass**

```bash
php artisan test --compact --filter="calendar data"
```
Expected: PASS (3 tests).

**Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 7: Commit**

```bash
git add app/Domain/Meeting/Controllers/MeetingController.php routes/web.php tests/Feature/Domain/Meeting/Controllers/MeetingControllerTest.php
git commit -m "feat: add calendar data JSON endpoint for meetings"
```

---

### Task 2: Add tab toggle to meetings index view

**Files:**
- Modify: `resources/views/meetings/index.blade.php`

**Step 1: Add the tab toggle**

In `resources/views/meetings/index.blade.php`, replace the header block (lines 6–14):

```blade
{{-- Header --}}
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Meetings</h1>
    <div class="flex items-center gap-3">
        {{-- View Toggle --}}
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm font-medium">
            <a href="{{ route('meetings.index', array_merge(request()->except('view'), ['view' => 'list'])) }}"
               class="px-4 py-2 transition-colors {{ request('view', 'list') !== 'calendar' ? 'bg-violet-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                List
            </a>
            <a href="{{ route('meetings.index', array_merge(request()->except('view'), ['view' => 'calendar'])) }}"
               class="px-4 py-2 border-l border-gray-200 dark:border-gray-700 transition-colors {{ request('view') === 'calendar' ? 'bg-violet-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                Calendar
            </a>
        </div>
        @can('create', \App\Domain\Meeting\Models\MinutesOfMeeting::class)
        <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            + New MOM
        </a>
        @endcan
    </div>
</div>
```

**Step 2: Wrap existing list content with conditional**

Wrap everything from the `{{-- Stat Cards --}}` comment down to the closing `</div>` (end of the white card with the table) with:

```blade
@if(request('view') !== 'calendar')
    {{-- ... existing stat cards, filter form, table, pagination ... --}}
@else
    @include('meetings.partials.calendar-view')
@endif
```

**Step 3: Verify visually**

Visit `https://antaraflow.test/meetings` — should see List/Calendar toggle in header. Clicking Calendar shows an empty div (partial not yet created). Clicking List restores the table.

**Step 4: Commit**

```bash
git add resources/views/meetings/index.blade.php
git commit -m "feat: add list/calendar tab toggle to meetings page"
```

---

### Task 3: Create the Alpine.js calendar grid partial

**Files:**
- Create: `resources/views/meetings/partials/calendar-view.blade.php`

**Step 1: Create the partial**

Create `resources/views/meetings/partials/calendar-view.blade.php`:

```blade
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
```

**Step 2: Verify in browser**

Visit `https://antaraflow.test/meetings?view=calendar`. You should see:
- Month navigation header with current month name
- 7-column grid with day numbers
- Meeting chips on correct days (coloured by status)
- Click a chip → modal opens with details + "View Meeting" link
- `<` / `>` buttons navigate months and reload meetings

**Step 3: Run full test suite to check for regressions**

```bash
php artisan test --compact
```
Expected: all tests pass.

**Step 4: Commit**

```bash
git add resources/views/meetings/partials/calendar-view.blade.php
git commit -m "feat: add monthly calendar view for meetings with Alpine.js"
```

---

## Done

All three tasks complete. The feature is fully functional:
- `GET /meetings/calendar-data` returns meeting JSON for any month
- `GET /meetings?view=calendar` shows the monthly grid
- Meeting chips coloured by status, click opens modal preview
- Modal has "View Meeting" link to full meeting page
- Month navigation fetches fresh data via AJAX
