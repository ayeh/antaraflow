# Dashboard Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform the Dashboard from a generic stats display into a personal workspace — showing "what do I need to do right now?" with personalized stat cards, a conditional "Needs Attention" banner, this week's meetings, my action items, and recent org MOM activity.

**Architecture:** Pure Blade + Tailwind CSS + Alpine.js. Two files modified: `DashboardController` (new queries, updated `$stats`) and `dashboard.blade.php` (full rewrite). No new routes, models, or services. Reuses `meetings.partials._status-badge` partial for status badges.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, Alpine.js, Heroicons inline SVG, `App\Domain\Account\Models\AuditLog`, `App\Support\Enums\ActionItemStatus`, `App\Support\Enums\ActionItemPriority`, `App\Support\Enums\MeetingStatus`

---

## Context

**Files to touch:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Rewrite: `resources/views/dashboard.blade.php`
- Modify: `tests/Feature/DashboardTest.php`

**Data available after Task 1:**
- `$stats` — personalized: `my_actions`, `my_overdue`, `meetings_this_week`, `pending_approval`, `my_completion_rate`
- `$thisWeekMeetings` — meetings with `meeting_date` Mon–Sun this week, with `project`
- `$upcomingActions` — action items assigned to me, open/in_progress, ordered by due_date, max 5
- `$recentActivity` — last 8 AuditLog entries for MinutesOfMeeting in org, with `user` and `auditable`
- `$canCreateMeeting` — bool

**Design System tokens (from `2026-03-17`):**
- Card: `bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700`
- Section header: `text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3`
- Status badge: `@include('meetings.partials._status-badge', ['status' => $meeting->status])`

**AuditLog actions for MinutesOfMeeting:** `created`, `updated`, `finalized`, `approved`, `reverted_to_draft`, `deleted`
**AuditLog `auditable_type`:** `App\Domain\Meeting\Models\MinutesOfMeeting` (no morphMap registered)

---

## Task 1: Extend DashboardController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

**Step 1: Open the file**

Current file is at `app/Http/Controllers/DashboardController.php`. Read it to understand existing queries.

**Step 2: Replace the controller with the new version**

Replace entire file contents with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Services\AuthorizationService;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AuthorizationService $authorizationService) {}

    public function index(): View
    {
        $user = auth()->user();
        $orgId = $user->current_organization_id;

        // Personalized stat card data
        $myActionsCount = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->count();

        $myOverdueCount = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->where('due_date', '<', now())
            ->count();

        $meetingsThisWeek = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $pendingApproval = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->where('status', MeetingStatus::Finalized)
            ->count();

        $myTotal = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->count();

        $myCompleted = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->where('status', ActionItemStatus::Completed)
            ->count();

        $stats = [
            'my_actions'          => $myActionsCount,
            'my_overdue'          => $myOverdueCount,
            'meetings_this_week'  => $meetingsThisWeek,
            'pending_approval'    => $pendingApproval,
            'my_completion_rate'  => $myTotal > 0 ? (int) round(($myCompleted / $myTotal) * 100) : 0,
        ];

        // This week's meetings for the left column
        $thisWeekMeetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->with(['project'])
            ->orderBy('meeting_date')
            ->get();

        // My upcoming action items (assigned to me, not done)
        $upcomingActions = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->orderBy('due_date')
            ->take(5)
            ->get();

        // Recent org-wide MOM audit activity
        $recentActivity = AuditLog::query()
            ->where('organization_id', $orgId)
            ->where('auditable_type', MinutesOfMeeting::class)
            ->whereIn('action', ['created', 'finalized', 'approved', 'reverted_to_draft'])
            ->with(['user', 'auditable'])
            ->latest()
            ->take(8)
            ->get();

        $canCreateMeeting = $this->authorizationService->hasPermission($user, $user->currentOrganization, 'create_meeting');

        return view('dashboard', compact(
            'stats',
            'myOverdueCount',
            'pendingApproval',
            'thisWeekMeetings',
            'upcomingActions',
            'recentActivity',
            'canCreateMeeting',
        ));
    }
}
```

**Step 3: Run pint**

Run: `vendor/bin/pint app/Http/Controllers/DashboardController.php --format agent`

**Step 4: Verify page loads**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: tests may fail (we'll fix them next task) but no PHP errors.

**Step 5: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "feat: extend DashboardController with personalized workspace data"
```

---

## Task 2: Update DashboardTest

**Files:**
- Modify: `tests/Feature/DashboardTest.php`

**Step 1: Replace file contents**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('authenticated user sees dashboard', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Dashboard');
});

test('guest is redirected from dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('stat cards show personalized counts', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('My Actions');
    $response->assertSee('Overdue');
    $response->assertSee('This Week');
    $response->assertSee('Pending Approval');
    $response->assertSee('Completion');
});

test('needs attention banner shows when user has overdue actions', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
        'due_date' => now()->subDays(3),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Needs Attention');
    $response->assertSee('overdue');
});

test('needs attention banner hidden when no urgent items', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertDontSee('Needs Attention');
});

test('needs attention banner shows pending approval when finalized moms exist', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::Finalized,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Needs Attention');
    $response->assertSee('pending approval');
});

test('this weeks meetings section shows current week meetings', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Standup',
        'meeting_date' => now()->startOfWeek()->addDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('This Week');
    $response->assertSee('Weekly Standup');
});

test('my action items section shows assigned items', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => $meeting->id,
        'title' => 'Write quarterly report',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('My Action Items');
    $response->assertSee('Write quarterly report');
});

test('recent activity shows mom audit log entries', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Board Meeting',
    ]);

    AuditLog::factory()->create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'action' => 'created',
        'auditable_type' => MinutesOfMeeting::class,
        'auditable_id' => $meeting->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSee('Recent Activity');
});
```

**Step 2: Check if AuditLog factory exists**

Run: `php artisan tinker --execute="echo class_exists(Database\Factories\AuditLogFactory::class) ? 'exists' : 'missing';"`

If factory is missing, skip the `recent activity` test (comment it out) for now.

**Step 3: Run tests**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: All tests fail (view not updated yet). That's correct — we verify the tests exist and run.

**Step 4: Commit**

```bash
git add tests/Feature/DashboardTest.php
git commit -m "test: update DashboardTest for new workspace data contract"
```

---

## Task 3: Rewrite dashboard.blade.php — Page Header + Stat Cards

**Files:**
- Modify: `resources/views/dashboard.blade.php`

**Step 1: Replace entire file with this content (stat cards + banner only, main content placeholder)**

```blade
@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ now()->format('l, j F Y') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}"
               class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Analytics
            </a>
            @if($canCreateMeeting)
                <a href="{{ route('meetings.create') }}"
                   class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New MOM
                </a>
            @endif
        </div>
    </div>

    {{-- Stat Cards --}}
    @php
    $statCards = [
        [
            'label'  => 'My Actions',
            'count'  => $stats['my_actions'],
            'href'   => route('action-items.dashboard'),
            'color'  => 'violet',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        ],
        [
            'label'  => 'Overdue',
            'count'  => $stats['my_overdue'],
            'href'   => route('action-items.dashboard'),
            'color'  => 'red',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        [
            'label'  => 'This Week',
            'count'  => $stats['meetings_this_week'],
            'href'   => route('meetings.index', ['view' => 'calendar']),
            'color'  => 'blue',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        ],
        [
            'label'  => 'Pending Approval',
            'count'  => $stats['pending_approval'],
            'href'   => route('meetings.index', ['status' => 'finalized']),
            'color'  => 'amber',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
        [
            'label'  => 'Completion Rate',
            'count'  => $stats['my_completion_rate'] . '%',
            'href'   => route('action-items.dashboard'),
            'color'  => 'green',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        ],
    ];
    $colorMap = [
        'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900/30', 'icon' => 'text-violet-600 dark:text-violet-400', 'hover' => 'hover:border-violet-300 dark:hover:border-violet-700'],
        'red'    => ['bg' => 'bg-red-100 dark:bg-red-900/30',       'icon' => 'text-red-600 dark:text-red-400',       'hover' => 'hover:border-red-300 dark:hover:border-red-700'],
        'blue'   => ['bg' => 'bg-blue-100 dark:bg-blue-900/30',     'icon' => 'text-blue-600 dark:text-blue-400',     'hover' => 'hover:border-blue-300 dark:hover:border-blue-700'],
        'amber'  => ['bg' => 'bg-amber-100 dark:bg-amber-900/30',   'icon' => 'text-amber-600 dark:text-amber-400',   'hover' => 'hover:border-amber-300 dark:hover:border-amber-700'],
        'green'  => ['bg' => 'bg-green-100 dark:bg-green-900/30',   'icon' => 'text-green-600 dark:text-green-400',   'hover' => 'hover:border-green-300 dark:hover:border-green-700'],
    ];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach($statCards as $card)
            @php $c = $colorMap[$card['color']]; @endphp
            <a href="{{ $card['href'] }}"
               class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 transition-all hover:shadow-md {{ $c['hover'] }}"
               aria-label="{{ $card['label'] }}: {{ $card['count'] }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-9 h-9 rounded-xl {{ $c['bg'] }} flex items-center justify-center">
                        <svg class="w-5 h-5 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $card['icon'] !!}
                        </svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $card['count'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $card['label'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- Needs Attention Banner --}}
    @if($myOverdueCount > 0 || $pendingApproval > 0)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 space-y-2">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Needs Attention</span>
            </div>
            @if($myOverdueCount > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                        {{ $myOverdueCount }} action {{ Str::plural('item', $myOverdueCount) }} overdue
                    </span>
                    <a href="{{ route('action-items.dashboard') }}" class="text-amber-700 dark:text-amber-400 font-medium hover:underline text-xs">View all →</a>
                </div>
            @endif
            @if($pendingApproval > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                        {{ $pendingApproval }} {{ Str::plural('MOM', $pendingApproval) }} pending approval
                    </span>
                    <a href="{{ route('meetings.index', ['status' => 'finalized']) }}" class="text-amber-700 dark:text-amber-400 font-medium hover:underline text-xs">Review now →</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Main content placeholder --}}
    <div class="text-gray-400 text-sm">Main content coming in next tasks…</div>

</div>
@endsection
```

**Step 2: Run tests**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: `stat cards show personalized counts`, `needs attention banner shows`, `needs attention banner hidden` should now pass.

**Step 3: Run pint**

Run: `vendor/bin/pint resources/views/dashboard.blade.php --format agent`

**Step 4: Commit**

```bash
git add resources/views/dashboard.blade.php
git commit -m "feat: dashboard workspace — stat cards and needs attention banner"
```

---

## Task 4: Add Left Column — This Week's Meetings + My Action Items

**Files:**
- Modify: `resources/views/dashboard.blade.php`

**Step 1: Replace the `{{-- Main content placeholder --}}` line with the full two-column layout**

Find this line:
```blade
    {{-- Main content placeholder --}}
    <div class="text-gray-400 text-sm">Main content coming in next tasks…</div>
```

Replace with:

```blade
    {{-- Main Content: Two Column --}}
    <div class="flex flex-col lg:flex-row gap-6">

        {{-- LEFT COLUMN --}}
        <div class="flex-1 min-w-0 space-y-6">

            {{-- This Week's Meetings --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">This Week's Meetings</h2>
                    <a href="{{ route('meetings.index', ['view' => 'calendar']) }}"
                       class="text-xs text-violet-600 dark:text-violet-400 hover:underline font-medium">View calendar →</a>
                </div>
                @forelse($thisWeekMeetings as $meeting)
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors border-b border-gray-100 dark:border-slate-700/50 last:border-0">
                        {{-- Date badge --}}
                        <div class="shrink-0 w-11 text-center">
                            <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase leading-none">
                                {{ $meeting->meeting_date?->format('M') ?? '' }}
                            </div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                                {{ $meeting->meeting_date?->format('j') ?? '—' }}
                            </div>
                        </div>
                        {{-- Details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $meeting->title }}</span>
                                @include('meetings.partials._status-badge', ['status' => $meeting->status])
                            </div>
                            <div class="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                                @if($meeting->meeting_date)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $meeting->meeting_date->format('g:i A') }}
                                        @if($meeting->end_time)
                                            – {{ \Carbon\Carbon::parse($meeting->end_time)->format('g:i A') }}
                                        @endif
                                    </span>
                                @endif
                                @if($meeting->location)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                        {{ $meeting->location }}
                                    </span>
                                @endif
                                @if($meeting->project)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                        </svg>
                                        {{ $meeting->project->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center">
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm text-gray-400 dark:text-gray-500">No meetings scheduled this week</p>
                    </div>
                @endforelse
            </div>

            {{-- My Action Items --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">My Action Items</h2>
                    <a href="{{ route('action-items.dashboard') }}"
                       class="text-xs text-violet-600 dark:text-violet-400 hover:underline font-medium">View all →</a>
                </div>
                @forelse($upcomingActions as $action)
                    @php
                        $isOverdue = $action->due_date?->isPast();
                        $priorityDot = match($action->priority) {
                            \App\Support\Enums\ActionItemPriority::Critical => 'bg-red-500',
                            \App\Support\Enums\ActionItemPriority::High     => 'bg-orange-500',
                            \App\Support\Enums\ActionItemPriority::Medium   => 'bg-blue-500',
                            default                                          => 'bg-gray-400',
                        };
                    @endphp
                    <div class="flex items-start gap-3 px-5 py-4 border-b border-gray-100 dark:border-slate-700/50 last:border-0">
                        <span class="mt-1.5 w-2 h-2 rounded-full {{ $priorityDot }} shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $action->title }}</div>
                            <div class="mt-0.5 flex items-center gap-2 text-xs flex-wrap">
                                @if($action->due_date)
                                    <span class="{{ $isOverdue ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $isOverdue ? 'Overdue · ' : 'Due · ' }}{{ $action->due_date->format('j M Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">No due date</span>
                                @endif
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $action->priority->label() }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <p class="text-sm text-gray-400 dark:text-gray-500">No pending action items</p>
                    </div>
                @endforelse
            </div>

        </div>

        {{-- RIGHT COLUMN placeholder --}}
        <div class="w-full lg:w-80 shrink-0">
            <div class="text-gray-400 text-sm">Right column coming next…</div>
        </div>

    </div>
```

**Step 2: Run tests**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: `this weeks meetings` and `my action items` tests pass.

**Step 3: Run pint**

Run: `vendor/bin/pint resources/views/dashboard.blade.php --format agent`

**Step 4: Commit**

```bash
git add resources/views/dashboard.blade.php
git commit -m "feat: dashboard workspace — this week meetings and my action items columns"
```

---

## Task 5: Add Right Column — Recent MOM Activity

**Files:**
- Modify: `resources/views/dashboard.blade.php`

**Step 1: Replace the right column placeholder**

Find:
```blade
        {{-- RIGHT COLUMN placeholder --}}
        <div class="w-full lg:w-80 shrink-0">
            <div class="text-gray-400 text-sm">Right column coming next…</div>
        </div>
```

Replace with:

```blade
        {{-- RIGHT COLUMN: Recent Activity --}}
        <div class="w-full lg:w-80 shrink-0">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recent Activity</h2>
                    <a href="{{ route('meetings.index') }}"
                       class="text-xs text-violet-600 dark:text-violet-400 hover:underline font-medium">View all →</a>
                </div>
                @forelse($recentActivity as $log)
                    @php
                        $actionLabel = match($log->action) {
                            'created'          => 'created',
                            'finalized'        => 'finalized',
                            'approved'         => 'approved',
                            'reverted_to_draft' => 'reverted to draft',
                            default            => $log->action,
                        };
                        $meetingTitle = $log->auditable?->title ?? 'a meeting';
                        $initials = $log->user
                            ? collect(explode(' ', $log->user->name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join('')
                            : '?';
                        $actionColor = match($log->action) {
                            'approved'  => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                            'finalized' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                            'created'   => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
                            default     => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                        };
                    @endphp
                    <div class="flex items-start gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-slate-700/50 last:border-0">
                        {{-- Avatar --}}
                        <div class="w-7 h-7 rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">
                            {{ $initials }}
                        </div>
                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-snug">
                                <span class="font-medium">{{ $log->user?->name ?? 'System' }}</span>
                                <span class="inline-flex items-center mx-1 px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $actionColor }}">{{ $actionLabel }}</span>
                                <span class="truncate">{{ Str::limit($meetingTitle, 30) }}</span>
                            </p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $log->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-gray-400 dark:text-gray-500">No recent activity</p>
                    </div>
                @endforelse
            </div>
        </div>
```

**Step 2: Run all dashboard tests**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: All tests pass.

**Step 3: Run pint**

Run: `vendor/bin/pint resources/views/dashboard.blade.php --format agent`

**Step 4: Run full test suite to catch regressions**

Run: `php artisan test --compact`
Expected: All tests pass.

**Step 5: Commit**

```bash
git add resources/views/dashboard.blade.php
git commit -m "feat: dashboard workspace — recent MOM activity right column"
```

---

## Task 6: Build assets and manual verification

**Step 1: Build Tailwind**

Run: `npm run build`
Expected: Build completes without errors.

**Step 2: Verify in browser**

Navigate to: `https://antaraflow.test/dashboard`

Check:
- [ ] 5 stat cards render in a responsive grid (2-col mobile, 3-col tablet, 5-col desktop)
- [ ] Cards link to correct pages
- [ ] "Needs Attention" banner shows only when there are overdue actions or pending approvals (create a finalized MOM to test)
- [ ] "This Week's Meetings" shows meetings with `meeting_date` in current week
- [ ] Date badge shows correct day number + month abbreviation
- [ ] Status badge uses design system colors (from `_status-badge` partial)
- [ ] "My Action Items" shows items assigned to logged-in user
- [ ] Overdue items show red due date text
- [ ] "Recent Activity" shows audit log entries with user avatars + action labels
- [ ] Dark mode toggle works correctly
- [ ] Empty states show for sections with no data

**Step 3: Commit if any fixes were made**

```bash
git add resources/views/dashboard.blade.php app/Http/Controllers/DashboardController.php
git commit -m "fix: dashboard workspace post-QA adjustments"
```
