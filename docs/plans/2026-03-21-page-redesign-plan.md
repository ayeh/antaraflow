# Page Redesign — Projects, Analytics, Meetings Show

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Redesign 3 pages (Projects Index, Analytics Overview, Meetings Show) to match the antaraFLOW design system used in meetings/index.blade.php.

**Architecture:** Visual/CSS-only changes per page. Each task is one self-contained page. Design reference: `resources/views/meetings/index.blade.php`. Key patterns: `rounded-xl`, `dark:bg-slate-800`, `dark:border-slate-700`, violet accents, stat cards with icon backgrounds.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, Alpine.js

---

## Design System Cheatsheet (refer to this throughout)

```
Primary button:   bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium
Secondary button: bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 px-4 py-2 rounded-xl text-sm font-medium
Card:             bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700
Stat icon bg:     bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3
Table header:     bg-gray-50 dark:bg-slate-700/50 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider
Row hover:        hover:bg-gray-50 dark:hover:bg-slate-700/30
Dividers:         divide-gray-200 dark:divide-slate-700
Dark mode rule:   ALWAYS use dark:bg-slate-* and dark:border-slate-* (NOT dark:bg-gray-* or dark:border-gray-*)
```

---

### Task 1: Projects Index — Controller data for stat cards

**Files:**
- Find and modify: `app/Domain/Project/Controllers/ProjectController.php` (index method)

**Step 1: Read the controller**

Read the ProjectController to find the `index` method and see what it currently passes to the view.

**Step 2: Add stat card data**

In the `index` method, before returning the view, add these aggregate queries (use `Project::query()` with the same scope/filters already applied):

```php
$stats = [
    'total'    => Project::count(),
    'active'   => Project::where('is_active', true)->count(),
    'members'  => Project::withCount('members')->get()->sum('members_count'),
    'meetings' => Project::withCount('meetings')->get()->sum('meetings_count'),
];
```

Pass `$stats` to the view: `compact('projects', 'stats')`.

**Step 3: Run tests to confirm nothing broke**

```bash
php artisan test --compact --filter=Project
```

Expected: all Project tests pass.

**Step 4: Commit**

```bash
git add app/Domain/Project/Controllers/ProjectController.php
git commit -m "feat: pass stat card data to projects index view"
```

---

### Task 2: Projects Index — Full page redesign

**Files:**
- Modify: `resources/views/projects/index.blade.php`

**Step 1: Read the current file**

Read `resources/views/projects/index.blade.php` in full. You will replace it entirely.

**Step 2: Write the redesigned view**

Replace the entire file content with:

```blade
@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Projects</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage your organization's projects</p>
        </div>
        @can('create', \App\Domain\Project\Models\Project::class)
        <a href="{{ route('projects.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Project
        </a>
        @endcan
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Projects</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-green-100 dark:bg-green-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-blue-100 dark:bg-blue-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['members'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Members</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-purple-100 dark:bg-purple-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['meetings'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Meetings</div>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Members</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meetings</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse($projects as $project)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 transition-colors">{{ $project->name }}</a>
                                @if($project->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate max-w-xs">{{ $project->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $project->code ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $project->members_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $project->meetings_count }}</td>
                            <td class="px-6 py-4">
                                @if($project->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('projects.show', $project) }}" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 font-medium transition-colors">View</a>
                                    @can('update', $project)
                                    <a href="{{ route('projects.edit', $project) }}" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 font-medium transition-colors">Edit</a>
                                    @endcan
                                    @can('delete', $project)
                                    <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Are you sure you want to delete this project?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors">Delete</button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto w-12 h-12 bg-gray-100 dark:bg-slate-700 rounded-xl flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">No projects yet</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first project.</p>
                                @can('create', \App\Domain\Project\Models\Project::class)
                                <div class="mt-6">
                                    <a href="{{ route('projects.create') }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        New Project
                                    </a>
                                </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($projects->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700">
                {{ $projects->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
```

**Step 3: Run pint**

```bash
vendor/bin/pint resources/views/projects/index.blade.php --format agent
```

**Step 4: Run npm build**

```bash
npm run build
```

**Step 5: Verify visually**

Open `/projects` in browser. Check:
- Header with subtitle visible
- 4 stat cards in a grid with colored icons
- Table has correct dark mode styling
- Empty state has icon card

**Step 6: Commit**

```bash
git add resources/views/projects/index.blade.php
git commit -m "feat: redesign projects index with stat cards and design system"
```

---

### Task 3: Analytics Overview — Full page redesign

**Files:**
- Modify: `resources/views/analytics/index.blade.php`

**Step 1: Read the current file**

Read `resources/views/analytics/index.blade.php` in full.

**Step 2: Write the redesigned view**

Replace the entire file with:

```blade
@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Last 6 months &mdash; {{ now()->subMonths(6)->format('M Y') }} to {{ now()->format('M Y') }}
            </p>
        </div>
    </div>

    {{-- Tab Navigation (pill style) --}}
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-slate-800 rounded-xl w-fit">
        <a href="{{ route('analytics.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-400 shadow-sm">
            Overview
        </a>
        <a href="{{ route('analytics.governance') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors text-gray-600 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-slate-700/60">
            Governance
        </a>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ array_sum($meetingStats['meetings_per_month']) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Meetings</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-green-100 dark:bg-green-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($actionStats['completion_rate'], 1) }}%</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Completion Rate</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-red-100 dark:bg-red-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $actionStats['overdue'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Overdue Items</div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4">
            <div class="bg-orange-100 dark:bg-orange-900/30 rounded-xl p-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($meetingStats['avg_duration_minutes']) }}<span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">min</span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Avg Duration</div>
            </div>
        </div>
    </div>

    {{-- Charts: Meetings + Status --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Meetings per Month</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64" x-data="{}" x-init="
                    new Chart(document.getElementById('meetingsChart'), {
                        type: 'bar',
                        data: {
                            labels: {{ json_encode(array_keys($meetingStats['meetings_per_month'])) }},
                            datasets: [{ label: 'Meetings', data: {{ json_encode(array_values($meetingStats['meetings_per_month'])) }}, backgroundColor: '#7c3aed', borderRadius: 6 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                    });
                ">
                    <canvas id="meetingsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Status Distribution</h2>
            </div>
            <div class="p-6">
                <div class="relative h-64" x-data="{}" x-init="
                    new Chart(document.getElementById('statusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: {{ json_encode(array_map('ucfirst', array_keys($meetingStats['status_distribution']))) }},
                            datasets: [{ data: {{ json_encode(array_values($meetingStats['status_distribution'])) }}, backgroundColor: ['#6b7280', '#3b82f6', '#eab308', '#22c55e'], borderWidth: 2 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
                    });
                ">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Items + Top Attendees --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Action Items Summary</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="relative h-48" x-data="{}" x-init="
                    new Chart(document.getElementById('actionsChart'), {
                        type: 'bar',
                        data: {
                            labels: ['Completed', 'Pending', 'Overdue'],
                            datasets: [{ data: [{{ $actionStats['completed'] }}, {{ $actionStats['pending'] }}, {{ $actionStats['overdue'] }}], backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'], borderRadius: 6 }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                    });
                ">
                    <canvas id="actionsChart"></canvas>
                </div>
                <div class="grid grid-cols-4 gap-3 pt-2 border-t border-gray-100 dark:border-slate-700">
                    <div class="text-center">
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $actionStats['total'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ $actionStats['completed'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Done</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-yellow-500 dark:text-yellow-400">{{ $actionStats['pending'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-red-600 dark:text-red-400">{{ $actionStats['overdue'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Top Attendees</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $participationStats['total_attendees'] }} unique</span>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($participationStats['top_attendees'] as $attendee)
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $attendee['name'] }}</div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                            {{ $attendee['count'] }} {{ Str::plural('meeting', $attendee['count']) }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No attendance data available.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- AI Usage --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">AI Usage</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-slate-700">
            <div class="px-6 py-6 flex items-center gap-4">
                <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $aiStats['total_meetings_with_ai'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Meetings with AI Extraction</div>
                </div>
            </div>
            <div class="px-6 py-6 flex items-center gap-4">
                <div class="bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3 shrink-0">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $aiStats['total_action_items'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">AI-Generated Action Items</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
```

**Step 3: Run pint + build**

```bash
vendor/bin/pint resources/views/analytics/index.blade.php --format agent
npm run build
```

**Step 4: Verify visually**

Open `/analytics` in browser. Check:
- Pill-style tab nav (not underline)
- 4 stat cards with colored icons
- All chart cards have dark mode slate classes
- Top attendees badge is violet (not purple)

**Step 5: Commit**

```bash
git add resources/views/analytics/index.blade.php
git commit -m "feat: redesign analytics overview with pill tabs, stat cards, design system"
```

---

### Task 4: Meetings Show — Stepper redesign

**Files:**
- Read first: `resources/views/meetings/wizard/stepper.blade.php`
- Modify: `resources/views/meetings/wizard/stepper.blade.php`

**Step 1: Read the stepper file**

Read `resources/views/meetings/wizard/stepper.blade.php` in full to understand the current implementation.

**Step 2: Replace with redesigned stepper**

The stepper must show 5 steps: Setup, Attendees, Inputs, Review, Finalize.
It uses `activeStep` Alpine.js variable from the parent `x-data` scope.

Replace the stepper content with:

```blade
{{-- Redesigned Stepper --}}
@php
$steps = ['Setup', 'Attendees', 'Inputs', 'Review', 'Finalize'];
@endphp
<div class="flex items-center mb-6">
    @foreach($steps as $index => $label)
        @php $stepNumber = $index + 1; @endphp

        {{-- Step circle + label --}}
        <div class="flex flex-col items-center">
            <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-200"
                :class="
                    activeStep > {{ $stepNumber }}
                        ? 'bg-violet-600 text-white'
                        : activeStep === {{ $stepNumber }}
                            ? 'bg-violet-600 text-white ring-4 ring-violet-100 dark:ring-violet-900/30'
                            : 'bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-gray-400'
                "
            >
                <template x-if="activeStep > {{ $stepNumber }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </template>
                <template x-if="activeStep <= {{ $stepNumber }}">
                    <span>{{ $stepNumber }}</span>
                </template>
            </div>
            <span
                class="mt-1.5 text-xs font-medium transition-colors duration-200 whitespace-nowrap hidden sm:block"
                :class="activeStep >= {{ $stepNumber }} ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400 dark:text-slate-500'"
            >{{ $label }}</span>
        </div>

        {{-- Connector line (not after last step) --}}
        @if($stepNumber < count($steps))
        <div class="flex-1 h-0.5 mx-2 mb-5 transition-colors duration-200"
             :class="activeStep > {{ $stepNumber }} ? 'bg-violet-600' : 'bg-gray-200 dark:bg-slate-700'">
        </div>
        @endif
    @endforeach
</div>
```

**Step 3: Run pint**

```bash
vendor/bin/pint resources/views/meetings/wizard/stepper.blade.php --format agent
```

**Step 4: Verify visually**

Open any meeting at `/meetings/{id}`. Check:
- Numbered circles for each step
- Active step has violet fill + ring
- Completed steps show checkmark
- Connecting lines turn violet for completed steps
- Step labels visible on sm+ screens

**Step 5: Commit**

```bash
git add resources/views/meetings/wizard/stepper.blade.php
git commit -m "feat: redesign meeting stepper with numbered circles and violet progress"
```

---

### Task 5: Meetings Show — Header and buttons redesign

**Files:**
- Modify: `resources/views/meetings/show.blade.php`

**Step 1: Read the file**

Read `resources/views/meetings/show.blade.php` in full.

**Step 2: Redesign the header section**

Find the header section (lines 10–129 in current file). Replace it with a cleaner layout:

Key changes:
1. Add subtitle row: MOM number + date under title
2. All `rounded-lg` buttons → `rounded-xl`
3. All `dark:bg-gray-800` → `dark:bg-slate-800`
4. All `dark:border-gray-700` → `dark:border-slate-700`
5. All `dark:hover:bg-gray-700` → `dark:hover:bg-slate-700`
6. Export dropdown: `rounded-lg shadow-lg` → `rounded-xl shadow-xl`
7. Avatar ring: `dark:ring-gray-900` → `dark:ring-slate-900`
8. Status badge: existing classes are fine, just ensure dark mode uses `dark:bg-*-900/30`

Find and replace these specific strings throughout the file:

| Find | Replace |
|------|---------|
| `rounded-lg` (on buttons/dropdowns only, not form elements) | `rounded-xl` |
| `dark:bg-gray-800` | `dark:bg-slate-800` |
| `dark:border-gray-700` | `dark:border-slate-700` |
| `dark:hover:bg-gray-700` | `dark:hover:bg-slate-700` |
| `dark:ring-gray-900` | `dark:ring-slate-900` |
| `dark:bg-gray-700` (in avatar/badge contexts) | `dark:bg-slate-700` |

Also: Find the navigation buttons section (lines 135–148) and update:

Change the "Next" button from dark/black to violet:
```blade
{{-- Before --}}
class="inline-flex items-center gap-1 bg-gray-900 dark:bg-white dark:text-gray-900 text-white rounded-lg px-5 py-2 text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors"

{{-- After --}}
class="inline-flex items-center gap-1 bg-violet-600 text-white rounded-xl px-5 py-2 text-sm font-medium hover:bg-violet-700 transition-colors"
```

And the "Previous" button add consistent sizing:
```blade
{{-- Before --}}
class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"

{{-- After --}}
class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors px-4 py-2"
```

**Step 3: Run pint**

```bash
vendor/bin/pint resources/views/meetings/show.blade.php --format agent
```

**Step 4: Run npm build**

```bash
npm run build
```

**Step 5: Verify visually**

Open a meeting. Check:
- All buttons use `rounded-xl`
- Dark mode shows slate instead of gray
- Next button is violet (not black)
- Stepper looks correct

**Step 6: Run tests**

```bash
php artisan test --compact
```

Expected: all tests pass (pre-existing LiveMeeting failures are OK to ignore).

**Step 7: Commit**

```bash
git add resources/views/meetings/show.blade.php
git commit -m "feat: update meetings show header and buttons to design system"
```
