# Meeting Action Bar Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the cluttered 2-row action bar on `meetings/show.blade.php` with a clean single-row layout: a `[•••]` overflow dropdown on the left, primary context-aware CTA buttons on the right.

**Architecture:** Pure Blade + Alpine.js change. No PHP, no routes, no migrations. One file to edit: `resources/views/meetings/show.blade.php` lines 20–143. The overflow menu uses Alpine `x-data="{ moreOpen: false }"` and `@click.outside` to close. Status badge stays below the title (not in the button row).

**Tech Stack:** Blade, Alpine.js, Tailwind CSS (dark mode via `slate` tokens)

---

### Task 1: Restructure the header action bar

**Files:**
- Modify: `resources/views/meetings/show.blade.php:11-143`

**New layout spec:**

```
← Back to Meetings

[Title]                          [live avatars]  [status badge]
[MOM number]

                         [•••]  [Live Meeting?]  [Finalize | Approve]
```

**Step 1: Remove the old button row (lines 20–143) and replace with new structure**

The new `<div class="flex flex-wrap items-center gap-3">` (right side of header) should contain:

```html
{{-- Live Presence Avatars (keep as-is) --}}
{{-- Status Badge (keep as-is) --}}

{{-- [•••] Overflow Dropdown --}}
<div class="relative" x-data="{ moreOpen: false }">
    <button @click="moreOpen = !moreOpen" @keydown.escape.window="moreOpen = false"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
        </svg>
    </button>
    <div x-show="moreOpen" @click.outside="moreOpen = false" x-cloak x-transition
         class="absolute right-0 mt-1 w-52 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 py-1 z-30">

        {{-- Export section --}}
        <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Export</div>
        <a href="{{ route('meetings.export.pdf', $meeting) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
            <svg class="w-4 h-4 text-red-400" .../>  PDF
        </a>
        <a href="{{ route('meetings.export.word', $meeting) }}" ...> Word (.docx) </a>
        <a href="{{ route('meetings.export.csv', $meeting) }}" ...> CSV (Action Items) </a>
        <a href="{{ route('meetings.export.json', $meeting) }}" ...> JSON </a>

        <div class="my-1 border-t border-gray-100 dark:border-slate-700"></div>

        {{-- Actions --}}
        {{-- Duplicate --}}
        <form action="{{ route('meetings.duplicate', $meeting) }}" method="POST">
            @csrf
            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 text-left">
                Duplicate
            </button>
        </form>

        {{-- History --}}
        <a href="{{ route('meetings.versions.index', $meeting) }}" class="flex items-center gap-2 px-4 py-2 text-sm ...">
            History
        </a>

        {{-- Follow-up Email (conditional) --}}
        @if($meeting->extractions()->exists())
        <a href="{{ route('meetings.follow-up-email.generate', $meeting) }}" class="flex items-center gap-2 px-4 py-2 text-sm ...">
            Follow-up Email
        </a>
        @endif

        {{-- AI Prepare Agenda (conditional) --}}
        @if(($meeting->status === Draft || $meeting->status === InProgress) && $meeting->project_id)
            ... preparation-modal trigger button ...
        @endif

        {{-- Revert to Draft (conditional) --}}
        @if($meeting->status !== Draft)
        <div class="my-1 border-t border-gray-100 dark:border-slate-700"></div>
        <form method="POST" action="{{ route('meetings.revert', $meeting) }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-left">
                Revert to Draft
            </button>
        </form>
        @endif
    </div>
</div>

{{-- Primary CTAs (context-aware) --}}

{{-- Live Meeting: show when status != Approved and user can start live --}}
@can('startLive', $meeting)
    @if($meeting->status !== Approved)
        ... red Live Meeting button (keep exact same Alpine fetch logic) ...
    @endif
@endcan

{{-- Finalize: Draft or InProgress --}}
@if(status is Draft or InProgress)
    <form method="POST" ...><button class="bg-yellow-500 ...">Finalize</button></form>
@endif

{{-- Approve: Finalized only --}}
@if(status is Finalized)
    <form method="POST" ...><button class="bg-green-600 ...">Approve</button></form>
@endif
```

**Step 2: Verify in browser**
- Visit any meeting as demo@antaraflow.test
- Confirm single row layout
- Open `[•••]` and verify all items present
- Verify correct primary CTA shows per status

**Step 3: Run tests**
```bash
php artisan test --compact
```
Expected: all tests pass (no PHP changed, view-only)

**Step 4: Commit**
```bash
git add resources/views/meetings/show.blade.php
git commit -m "feat: redesign meeting action bar with overflow [•••] dropdown"
```
