# Action Items Sprint 2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add bulk actions (status/priority/delete), a slide-over quick edit drawer with activity timeline, and a kanban board view (with drag-and-drop) to the Action Items dashboard and per-meeting index.

**Architecture:** JSON API + Alpine.js. A new `ActionItemBulkController` handles multi-item operations. `ActionItemController::show` and `::update` gain `wantsJson()` branches for the slide-over. SortableJS powers kanban drag-and-drop. All features reuse the existing `PATCH /status` endpoint for status changes.

**Tech Stack:** Laravel 12, Alpine.js v3, Tailwind CSS v4, Blade components, SortableJS, Pest 4

**Design doc:** `docs/plans/2026-03-09-action-items-sprint2-design.md`

---

## Task 1: BulkActionItemRequest + ActionItemBulkController + route

**Files:**
- Create: `app/Domain/ActionItem/Requests/BulkActionItemRequest.php`
- Create: `app/Domain/ActionItem/Controllers/ActionItemBulkController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Domain/ActionItem/Controllers/ActionItemBulkControllerTest.php`

### Step 1: Write the failing tests

Run: `php artisan make:test --pest Domain/ActionItem/Controllers/ActionItemBulkControllerTest`

Replace the file contents with:

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    $this->items = ActionItem::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
        'priority' => ActionItemPriority::Medium,
    ]);
});

test('bulk status update changes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'status',
            'value' => 'in_progress',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'status' => 'in_progress']);
    }
});

test('bulk status update to completed sets completed_at', function () {
    $ids = $this->items->pluck('id')->toArray();

    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'status',
            'value' => 'completed',
        ]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'status' => 'completed']);
        expect(ActionItem::find($id)->completed_at)->not->toBeNull();
    }
});

test('bulk priority update changes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'priority',
            'value' => 'high',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'priority' => 'high']);
    }
});

test('bulk delete removes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'delete',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('action_items', ['id' => $id]);
    }
});

test('bulk action ignores ids from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherItem = ActionItem::factory()->create([
        'organization_id' => $otherOrg->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => [$otherItem->id],
            'action' => 'status',
            'value' => 'completed',
        ]);

    $response->assertOk()->assertJson(['updated' => 0]);
    $this->assertDatabaseHas('action_items', ['id' => $otherItem->id, 'status' => 'open']);
});

test('bulk action requires ids', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), ['action' => 'status', 'value' => 'completed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk status requires value', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $this->items->pluck('id')->toArray(),
            'action' => 'status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('bulk delete does not require value', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $this->items->pluck('id')->toArray(),
            'action' => 'delete',
        ])
        ->assertOk();
});

test('guest cannot perform bulk actions', function () {
    $this->postJson(route('action-items.bulk'), [
        'ids' => $this->items->pluck('id')->toArray(),
        'action' => 'status',
        'value' => 'completed',
    ])->assertUnauthorized();
});
```

### Step 2: Run tests to verify they fail

Run: `php artisan test --compact --filter=ActionItemBulkControllerTest`
Expected: FAIL — route not found / class not found errors

### Step 3: Create BulkActionItemRequest

Run: `php artisan make:request Domain/ActionItem/Requests/BulkActionItemRequest --no-interaction`

Replace the file at `app/Domain/ActionItem/Requests/BulkActionItemRequest.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'in:status,priority,delete'],
            'value' => [
                Rule::requiredIf(fn () => $this->input('action') !== 'delete'),
                'nullable',
                'string',
                $this->input('action') === 'status' ? Rule::enum(ActionItemStatus::class) : 'nullable',
                $this->input('action') === 'priority' ? Rule::enum(ActionItemPriority::class) : 'nullable',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'At least one item must be selected.',
            'ids.min' => 'At least one item must be selected.',
            'action.in' => 'The action must be status, priority, or delete.',
            'value.required_if' => 'A value is required for status and priority actions.',
        ];
    }
}
```

### Step 4: Create ActionItemBulkController

Run: `php artisan make:class app/Domain/ActionItem/Controllers/ActionItemBulkController --no-interaction`

Replace the file with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\BulkActionItemRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ActionItemBulkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function __invoke(BulkActionItemRequest $request): JsonResponse
    {
        $user = $request->user();
        $action = $request->validated('action');

        $items = ActionItem::query()
            ->whereIn('id', $request->validated('ids'))
            ->where('organization_id', $user->current_organization_id)
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['updated' => 0]);
        }

        $policyAbility = $action === 'delete' ? 'delete' : 'update';
        $this->authorize($policyAbility, $items->first());

        $updated = 0;

        foreach ($items as $item) {
            if ($action === 'status') {
                $this->actionItemService->changeStatus(
                    $item,
                    ActionItemStatus::from($request->validated('value')),
                    $user,
                );
            } elseif ($action === 'priority') {
                $item->update(['priority' => ActionItemPriority::from($request->validated('value'))]);
            } elseif ($action === 'delete') {
                $item->delete();
            }

            $updated++;
        }

        return response()->json(['updated' => $updated]);
    }
}
```

### Step 5: Add route

Open `routes/web.php`. After line with `action-items.dashboard`, add:

```php
Route::post('action-items/bulk', \App\Domain\ActionItem\Controllers\ActionItemBulkController::class)->name('action-items.bulk');
```

Also add the use statement at the top with the other ActionItem controller imports:
```php
use App\Domain\ActionItem\Controllers\ActionItemBulkController;
```

Then change the route line to:
```php
Route::post('action-items/bulk', ActionItemBulkController::class)->name('action-items.bulk');
```

### Step 6: Run tests to verify they pass

Run: `php artisan test --compact --filter=ActionItemBulkControllerTest`
Expected: All 9 tests PASS

### Step 7: Run pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 8: Commit

```bash
git add app/Domain/ActionItem/Requests/BulkActionItemRequest.php \
        app/Domain/ActionItem/Controllers/ActionItemBulkController.php \
        routes/web.php \
        tests/Feature/Domain/ActionItem/Controllers/ActionItemBulkControllerTest.php
git commit -m "feat: add ActionItemBulkController for bulk status/priority/delete"
```

---

## Task 2: Extend ActionItemController for JSON + fix completed_at in service

**Files:**
- Modify: `app/Domain/ActionItem/Services/ActionItemService.php` (lines ~48-65)
- Modify: `app/Domain/ActionItem/Controllers/ActionItemController.php`
- Modify: `tests/Feature/Domain/ActionItem/Controllers/ActionItemControllerTest.php`

### Step 1: Write the failing tests

Open `tests/Feature/Domain/ActionItem/Controllers/ActionItemControllerTest.php` and append these tests:

```php
test('show returns json when requested with accept header', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.action-items.show', [$this->meeting, $item]));

    $response->assertOk()
        ->assertJsonStructure([
            'id', 'title', 'description', 'status', 'priority',
            'due_date', 'assigned_to', 'meeting_id',
            'show_url', 'update_url', 'status_url',
            'users' => [['id', 'name']],
            'history',
        ]);

    expect($response->json('id'))->toBe($item->id);
});

test('update returns json when requested with accept header', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'title' => 'Updated Title',
            'priority' => 'high',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'id', 'title', 'status', 'priority', 'priority_label',
            'priority_color_class', 'due_date', 'due_date_formatted',
            'assigned_to', 'assigned_to_name',
        ]);

    expect($response->json('title'))->toBe('Updated Title')
        ->and($response->json('priority'))->toBe('high');
});

test('update sets completed_at when status changes to completed', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'status' => 'completed',
        ]);

    expect(ActionItem::find($item->id)->completed_at)->not->toBeNull();
});

test('update clears completed_at when status changes away from completed', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Completed,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'status' => 'open',
        ]);

    expect(ActionItem::find($item->id)->completed_at)->toBeNull();
});
```

### Step 2: Run tests to verify they fail

Run: `php artisan test --compact --filter="show returns json|update returns json|sets completed_at|clears completed_at"`
Expected: FAIL

### Step 3: Fix ActionItemService::update() to handle completed_at

In `app/Domain/ActionItem/Services/ActionItemService.php`, replace the `update()` method body after the foreach loop (the `$item->update($data);` line) with:

```php
    /** @param  array<string, mixed>  $data */
    public function update(ActionItem $item, array $data, User $user): ActionItem
    {
        foreach ($data as $field => $newValue) {
            $oldValue = $item->getAttribute($field);
            if ($oldValue != $newValue) {
                $item->histories()->create([
                    'changed_by' => $user->id,
                    'field_changed' => $field,
                    'old_value' => $oldValue instanceof \BackedEnum ? $oldValue->value : (string) $oldValue,
                    'new_value' => $newValue instanceof \BackedEnum ? $newValue->value : (string) $newValue,
                ]);
            }
        }

        $updateData = $data;

        if (array_key_exists('status', $data)) {
            $newStatus = $data['status'] instanceof ActionItemStatus
                ? $data['status']
                : ActionItemStatus::tryFrom((string) $data['status']);

            if ($newStatus === ActionItemStatus::Completed && $item->status !== ActionItemStatus::Completed) {
                $updateData['completed_at'] = now();
            } elseif ($newStatus !== null && $newStatus !== ActionItemStatus::Completed) {
                $updateData['completed_at'] = null;
            }
        }

        $item->update($updateData);

        $item = $item->fresh();
        ActionItemUpdated::dispatch($item);

        return $item;
    }
```

### Step 4: Extend ActionItemController::show for JSON

In `app/Domain/ActionItem/Controllers/ActionItemController.php`, add `use App\Models\User;` to the imports, then replace the `show()` method with:

```php
    public function show(MinutesOfMeeting $meeting, ActionItem $actionItem): \Illuminate\View\View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $actionItem);

        $actionItem->load(['assignedTo', 'createdBy', 'histories.changedBy', 'carriedFrom', 'carriedTo']);

        if (request()->wantsJson()) {
            $users = User::where('current_organization_id', auth()->user()->current_organization_id)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'id' => $actionItem->id,
                'title' => $actionItem->title,
                'description' => $actionItem->description,
                'status' => $actionItem->status->value,
                'priority' => $actionItem->priority->value,
                'due_date' => $actionItem->due_date?->format('Y-m-d'),
                'assigned_to' => $actionItem->assigned_to,
                'meeting_id' => $meeting->id,
                'show_url' => route('meetings.action-items.show', [$meeting, $actionItem]),
                'update_url' => route('meetings.action-items.update', [$meeting, $actionItem]),
                'status_url' => route('meetings.action-items.status', [$meeting, $actionItem]),
                'users' => $users,
                'history' => $actionItem->histories->sortByDesc('created_at')->map(fn ($h) => [
                    'id' => $h->id,
                    'changed_by_name' => $h->changedBy?->name ?? 'Someone',
                    'field_changed' => $h->field_changed,
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'comment' => $h->comment,
                    'has_comment' => (bool) $h->comment,
                    'created_at_human' => $h->created_at->diffForHumans(),
                    'created_at_formatted' => $h->created_at->format('M j, Y H:i'),
                    'old_label' => \App\Support\Enums\ActionItemStatus::tryFrom($h->old_value)?->label() ?? $h->old_value,
                    'new_label' => \App\Support\Enums\ActionItemStatus::tryFrom($h->new_value)?->label() ?? $h->new_value,
                    'new_color_class' => \App\Support\Enums\ActionItemStatus::tryFrom($h->new_value)?->colorClass() ?? 'bg-gray-100 text-gray-600',
                    'status_changed' => $h->old_value !== $h->new_value,
                ])->values(),
            ]);
        }

        return view('action-items.show', compact('meeting', 'actionItem'));
    }
```

### Step 5: Extend ActionItemController::update for JSON

Replace the `update()` method return type and body:

```php
    public function update(UpdateActionItemRequest $request, MinutesOfMeeting $meeting, ActionItem $actionItem): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $actionItem);

        $this->actionItemService->update($actionItem, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            $actionItem->refresh()->load('assignedTo');

            return response()->json([
                'id' => $actionItem->id,
                'title' => $actionItem->title,
                'description' => $actionItem->description,
                'status' => $actionItem->status->value,
                'status_label' => $actionItem->status->label(),
                'status_color_class' => $actionItem->status->colorClass(),
                'priority' => $actionItem->priority->value,
                'priority_label' => $actionItem->priority->label(),
                'priority_color_class' => $actionItem->priority->colorClass(),
                'due_date' => $actionItem->due_date?->format('Y-m-d'),
                'due_date_formatted' => $actionItem->due_date?->format('M j, Y'),
                'due_date_past' => $actionItem->due_date?->isPast() && $actionItem->status !== \App\Support\Enums\ActionItemStatus::Completed,
                'assigned_to' => $actionItem->assigned_to,
                'assigned_to_name' => $actionItem->assignedTo?->name,
            ]);
        }

        return redirect()->route('meetings.action-items.show', [$meeting, $actionItem])
            ->with('success', 'Action item updated successfully.');
    }
```

### Step 6: Run tests

Run: `php artisan test --compact --filter=ActionItemControllerTest`
Expected: All tests PASS including the 4 new ones

### Step 7: Run pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 8: Commit

```bash
git add app/Domain/ActionItem/Services/ActionItemService.php \
        app/Domain/ActionItem/Controllers/ActionItemController.php \
        tests/Feature/Domain/ActionItem/Controllers/ActionItemControllerTest.php
git commit -m "feat: extend ActionItemController with JSON responses and fix completed_at on update"
```

---

## Task 3: Install SortableJS

**Files:**
- Modify: `package.json` (via npm install)
- Modify: `resources/js/app.js`

### Step 1: Install SortableJS

Run: `npm install sortablejs`

### Step 2: Expose SortableJS globally in app.js

Open `resources/js/app.js` and add after the `import Alpine from 'alpinejs';` line:

```js
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

### Step 3: Build assets

Run: `npm run build`
Expected: Build completes without errors

### Step 4: Commit

```bash
git add package.json package-lock.json resources/js/app.js
git commit -m "feat: install SortableJS and expose globally for kanban drag-and-drop"
```

---

## Task 4: Build slide-over Blade component

**Files:**
- Create: `resources/views/components/action-items/slide-over.blade.php`

### Step 1: Create the component file

Create `resources/views/components/action-items/slide-over.blade.php` with these contents:

```blade
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
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
            try {
                const res = await fetch(this.item.update_url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        ...this.form,
                        assigned_to: this.form.assigned_to || null,
                        due_date: this.form.due_date || null,
                    }),
                });
                if (!res.ok) { throw new Error('Failed'); }
                const data = await res.json();
                $dispatch('action-item-updated', { id: this.item.id, ...data });
                $dispatch('action-item-status-changed', { id: this.item.id, status: data.status });
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
                                            <span> added a note</span>
                                        </template>
                                    </p>
                                    <p
                                        x-show="entry.comment"
                                        class="text-xs text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 mt-1"
                                        x-text="'&quot;' + entry.comment + '&quot;'"
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
```

### Step 2: Commit

```bash
git add resources/views/components/action-items/slide-over.blade.php
git commit -m "feat: add slide-over quick edit drawer component"
```

---

## Task 5: Build kanban-board and view-toggle components

**Files:**
- Create: `resources/views/components/action-items/kanban-board.blade.php`
- Create: `resources/views/components/action-items/view-toggle.blade.php`

### Step 1: Create view-toggle component

Create `resources/views/components/action-items/view-toggle.blade.php`:

```blade
@props(['currentView' => 'table', 'tableUrl', 'kanbanUrl'])

<div class="flex items-center gap-1 bg-gray-100 dark:bg-slate-700 rounded-lg p-1">
    <a
        href="{{ $tableUrl }}"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors
            {{ $currentView === 'table'
                ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
    >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/>
        </svg>
        Table
    </a>
    <a
        href="{{ $kanbanUrl }}"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors
            {{ $currentView === 'kanban'
                ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
    >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
        </svg>
        Kanban
    </a>
</div>
```

### Step 2: Create kanban-board component

Create `resources/views/components/action-items/kanban-board.blade.php`:

```blade
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

        columnItems(statusValue) {
            return this.items.filter(i => i.status === statusValue);
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ status: newStatus }),
                });
                if (!res.ok) { throw new Error('Failed'); }
                $dispatch('action-item-status-changed', { id: itemId, status: newStatus });
            } catch {
                item.status = prev;
                alert('Failed to update status. Please try again.');
            }
        }
    }"
    x-init="
        $nextTick(() => {
            columns.forEach(col => {
                const el = document.getElementById('kanban-col-' + col.value);
                if (!el || typeof Sortable === 'undefined') { return; }
                Sortable.create(el, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'opacity-40',
                    dragClass: 'shadow-lg',
                    onEnd: (evt) => {
                        if (evt.from === evt.to) { return; }
                        const itemId = parseInt(evt.item.dataset.id);
                        const newStatus = evt.to.dataset.status;
                        const statusUrl = evt.item.dataset.statusUrl;
                        moveItem(itemId, newStatus, statusUrl);
                    }
                });
            });
        })
    "
    class="flex gap-4 overflow-x-auto pb-4"
>
    <template x-for="col in columns" :key="col.value">
        <div class="flex-shrink-0 w-72 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 flex flex-col max-h-[calc(100vh-14rem)]">
            {{-- Column header --}}
            <div class="px-4 py-3 flex items-center justify-between border-b border-gray-200 dark:border-slate-600 flex-shrink-0">
                <span :class="col.colorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="col.label"></span>
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500" x-text="columnItems(col.value).length"></span>
            </div>
            {{-- Cards --}}
            <div
                :id="'kanban-col-' + col.value"
                :data-status="col.value"
                class="flex-1 overflow-y-auto p-3 space-y-2 min-h-20"
            >
                <template x-for="item in columnItems(col.value)" :key="item.id">
                    <div
                        :data-id="item.id"
                        :data-status-url="item.status_url"
                        class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 p-3 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-shadow select-none"
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
```

### Step 3: Commit

```bash
git add resources/views/components/action-items/view-toggle.blade.php \
        resources/views/components/action-items/kanban-board.blade.php
git commit -m "feat: add kanban-board and view-toggle Blade components"
```

---

## Task 6: Update dashboard.blade.php

**Files:**
- Modify: `resources/views/action-items/dashboard.blade.php`
- Modify: `app/Domain/ActionItem/Controllers/ActionItemDashboardController.php`

The dashboard needs: bulk checkboxes, floating action bar, slide-over trigger, view toggle, and kanban view.

### Step 1: Update ActionItemDashboardController to pass view mode

In `ActionItemDashboardController::index()`, read the `view` param and pass it to the view. Replace the `return view(...)` line:

```php
        $currentView = in_array($request->query('view'), ['table', 'kanban']) ? $request->query('view') : 'table';

        return view('action-items.dashboard', compact(
            'actionItems',
            'selectedStatuses',
            'selectedPriorities',
            'assigneeFilter',
            'currentView',
        ));
```

### Step 2: Replace dashboard.blade.php

Replace the entire `resources/views/action-items/dashboard.blade.php` with:

```blade
@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items</h1>
        <x-action-items.view-toggle
            :currentView="$currentView"
            :tableUrl="route('action-items.dashboard', array_merge(request()->query(), ['view' => 'table']))"
            :kanbanUrl="route('action-items.dashboard', array_merge(request()->query(), ['view' => 'kanban']))"
        />
    </div>

    {{-- Filter Bar (table view only) --}}
    @if($currentView === 'table')
        <x-action-items.filter-bar
            :selectedStatuses="$selectedStatuses"
            :selectedPriorities="$selectedPriorities"
            :assigneeFilter="$assigneeFilter"
        />
    @endif

    @if($currentView === 'kanban')
        {{-- Kanban Board --}}
        <x-action-items.kanban-board :actionItems="$actionItems" :showMeeting="true" />
    @else
        {{-- Table --}}
        <div
            x-data="{
                selected: [],
                totalItems: {{ $actionItems->count() }},
                get allSelected() { return this.selected.length === this.totalItems && this.totalItems > 0; },
                toggleAll() {
                    if (this.allSelected) {
                        this.selected = [];
                    } else {
                        this.selected = [{{ $actionItems->pluck('id')->join(', ') }}];
                    }
                },
                toggle(id) {
                    this.selected.includes(id)
                        ? this.selected = this.selected.filter(i => i !== id)
                        : this.selected.push(id);
                },
                async applyBulk(action, value = null) {
                    if (this.selected.length === 0) { return; }
                    if (action === 'delete' && !confirm(`Delete ${this.selected.length} item(s)? This cannot be undone.`)) { return; }
                    try {
                        const res = await fetch('{{ route('action-items.bulk') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ ids: this.selected, action, value }),
                        });
                        if (!res.ok) { throw new Error('Failed'); }
                        window.location.reload();
                    } catch {
                        alert('Bulk action failed. Please try again.');
                    }
                }
            }"
        >
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        :indeterminate="selected.length > 0 && !allSelected"
                                        @change="toggleAll()"
                                        class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                    >
                                </th>
                                <th class="w-10 px-2 py-3"></th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meeting</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @forelse($actionItems as $item)
                                <tr
                                    class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
                                    x-data="{
                                        completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                        priorityLabel: @js($item->priority->label()),
                                        priorityColorClass: @js($item->priority->colorClass()),
                                        assigneeName: @js($item->assignedTo?->name ?? '—'),
                                        dueDateFormatted: @js($item->due_date?->format('M j, Y') ?? '—'),
                                        dueDatePast: {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                    }"
                                    :class="completed ? 'opacity-60' : ''"
                                    @action-item-updated.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            priorityLabel = $event.detail.priority_label;
                                            priorityColorClass = $event.detail.priority_color_class;
                                            assigneeName = $event.detail.assigned_to_name ?? '—';
                                            dueDateFormatted = $event.detail.due_date_formatted ?? '—';
                                            dueDatePast = $event.detail.due_date_past ?? false;
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                    @action-item-status-changed.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                >
                                    {{-- Select checkbox --}}
                                    <td class="px-4 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="selected.includes({{ $item->id }})"
                                            @change="toggle({{ $item->id }})"
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    {{-- Quick Complete Checkbox --}}
                                    <td class="px-2 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="completed"
                                            @change="
                                                completed = !completed;
                                                const newStatus = completed ? 'completed' : 'open';
                                                fetch('{{ route('meetings.action-items.status', [$item->meeting, $item]) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Accept': 'application/json',
                                                    },
                                                    body: JSON.stringify({ status: newStatus }),
                                                }).then(res => {
                                                    if (res.ok) {
                                                        $dispatch('action-item-status-changed', { id: {{ $item->id }}, status: newStatus });
                                                    } else {
                                                        completed = !completed;
                                                        alert('Failed to update. Please try again.');
                                                    }
                                                }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                            "
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    <td class="px-6 py-4">
                                        <button
                                            type="button"
                                            @click="$dispatch('open-slide-over', { meetingId: {{ $item->meeting->id }}, itemId: {{ $item->id }} })"
                                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 text-left"
                                        >{{ $item->title }}</button>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('meetings.show', $item->meeting) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400">{{ $item->meeting->title }}</a>
                                    </td>

                                    {{-- Inline Status Badge --}}
                                    <td class="px-6 py-4">
                                        <x-action-item-status-badge :item="$item" :meeting="$item->meeting" />
                                    </td>

                                    <td class="px-6 py-4">
                                        <span :class="priorityColorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="priorityLabel"></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" x-text="assigneeName"></td>
                                    <td class="px-6 py-4 text-sm" :class="dueDatePast ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'" x-text="dueDateFormatted"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-16 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                        @if($selectedStatuses || $selectedPriorities || $assigneeFilter)
                                            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No items match your filters</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('action-items.dashboard') }}" class="text-violet-600 hover:underline">Clear filters</a> to see all items.</p>
                                        @else
                                            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No action items yet</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Action items from your meetings will appear here.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Floating Bulk Action Bar --}}
            <div
                x-show="selected.length > 0"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-xl px-4 py-3"
                style="display: none;"
            >
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="selected.length + ' selected'"></span>
                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>

                <select
                    @change="if ($event.target.value) { applyBulk('status', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set status…</option>
                    @foreach(\App\Support\Enums\ActionItemStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>

                <select
                    @change="if ($event.target.value) { applyBulk('priority', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set priority…</option>
                    @foreach(\App\Support\Enums\ActionItemPriority::cases() as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    @click="applyBulk('delete')"
                    class="text-sm font-medium px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                >
                    Delete
                </button>

                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>
                <button type="button" @click="selected = []" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>

{{-- Slide-over (shared between table and kanban) --}}
<x-action-items.slide-over />
@endsection
```

### Step 3: Commit

```bash
git add resources/views/action-items/dashboard.blade.php \
        app/Domain/ActionItem/Controllers/ActionItemDashboardController.php
git commit -m "feat: add bulk actions, slide-over, view toggle, and kanban to action items dashboard"
```

---

## Task 7: Update index.blade.php (per-meeting action items)

**Files:**
- Modify: `resources/views/action-items/index.blade.php`
- Modify: `app/Domain/ActionItem/Controllers/ActionItemController.php` (index method)

### Step 1: Update ActionItemController::index to pass view param

In `ActionItemController::index()`, replace `return view(...)` with:

```php
        $currentView = in_array(request()->query('view'), ['table', 'kanban']) ? request()->query('view') : 'table';

        return view('action-items.index', compact('meeting', 'actionItems', 'currentView'));
```

### Step 2: Replace index.blade.php

Replace the entire `resources/views/action-items/index.blade.php` with:

```blade
@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('meetings.show', $meeting) }}" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items &mdash; {{ $meeting->title }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <x-action-items.view-toggle
                :currentView="$currentView"
                :tableUrl="route('meetings.action-items.index', [$meeting, 'view' => 'table'])"
                :kanbanUrl="route('meetings.action-items.index', [$meeting, 'view' => 'kanban'])"
            />
            <a href="{{ route('meetings.action-items.create', $meeting) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Action Item
            </a>
        </div>
    </div>

    @if($currentView === 'kanban')
        {{-- Kanban Board --}}
        <x-action-items.kanban-board :actionItems="$actionItems" :showMeeting="false" />
    @else
        {{-- Table --}}
        <div
            x-data="{
                selected: [],
                totalItems: {{ $actionItems->count() }},
                get allSelected() { return this.selected.length === this.totalItems && this.totalItems > 0; },
                toggleAll() {
                    this.selected = this.allSelected ? [] : [{{ $actionItems->pluck('id')->join(', ') }}];
                },
                toggle(id) {
                    this.selected.includes(id)
                        ? this.selected = this.selected.filter(i => i !== id)
                        : this.selected.push(id);
                },
                async applyBulk(action, value = null) {
                    if (this.selected.length === 0) { return; }
                    if (action === 'delete' && !confirm(`Delete ${this.selected.length} item(s)? This cannot be undone.`)) { return; }
                    try {
                        const res = await fetch('{{ route('action-items.bulk') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ ids: this.selected, action, value }),
                        });
                        if (!res.ok) { throw new Error('Failed'); }
                        window.location.reload();
                    } catch {
                        alert('Bulk action failed. Please try again.');
                    }
                }
            }"
        >
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        :indeterminate="selected.length > 0 && !allSelected"
                                        @change="toggleAll()"
                                        class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                    >
                                </th>
                                <th class="w-10 px-2 py-3"></th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @forelse($actionItems as $item)
                                <tr
                                    class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
                                    x-data="{
                                        completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                        priorityLabel: @js($item->priority->label()),
                                        priorityColorClass: @js($item->priority->colorClass()),
                                        assigneeName: @js($item->assignedTo?->name ?? '—'),
                                        dueDateFormatted: @js($item->due_date?->format('M j, Y') ?? '—'),
                                        dueDatePast: {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }},
                                    }"
                                    :class="completed ? 'opacity-60' : ''"
                                    @action-item-updated.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            priorityLabel = $event.detail.priority_label;
                                            priorityColorClass = $event.detail.priority_color_class;
                                            assigneeName = $event.detail.assigned_to_name ?? '—';
                                            dueDateFormatted = $event.detail.due_date_formatted ?? '—';
                                            dueDatePast = $event.detail.due_date_past ?? false;
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                    @action-item-status-changed.window="
                                        if ($event.detail.id === {{ $item->id }}) {
                                            completed = $event.detail.status === 'completed';
                                        }
                                    "
                                >
                                    {{-- Select checkbox --}}
                                    <td class="px-4 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="selected.includes({{ $item->id }})"
                                            @change="toggle({{ $item->id }})"
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    {{-- Quick Complete Checkbox --}}
                                    <td class="px-2 py-4">
                                        <input
                                            type="checkbox"
                                            :checked="completed"
                                            @change="
                                                completed = !completed;
                                                fetch('{{ route('meetings.action-items.status', [$meeting, $item]) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Accept': 'application/json',
                                                    },
                                                    body: JSON.stringify({ status: completed ? 'completed' : 'open' }),
                                                }).then(res => {
                                                    if (res.ok) {
                                                        $dispatch('action-item-status-changed', { id: {{ $item->id }}, status: completed ? 'completed' : 'open' });
                                                    } else {
                                                        completed = !completed;
                                                        alert('Failed to update. Please try again.');
                                                    }
                                                }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                            "
                                            class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                        >
                                    </td>

                                    <td class="px-6 py-4">
                                        <button
                                            type="button"
                                            @click="$dispatch('open-slide-over', { meetingId: {{ $meeting->id }}, itemId: {{ $item->id }} })"
                                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400 text-left"
                                        >{{ $item->title }}</button>
                                    </td>

                                    {{-- Inline Status Badge --}}
                                    <td class="px-6 py-4">
                                        <x-action-item-status-badge :item="$item" :meeting="$meeting" />
                                    </td>

                                    <td class="px-6 py-4">
                                        <span :class="priorityColorClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="priorityLabel"></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" x-text="assigneeName"></td>
                                    <td class="px-6 py-4 text-sm" :class="dueDatePast ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'" x-text="dueDateFormatted"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No action items yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Floating Bulk Action Bar --}}
            <div
                x-show="selected.length > 0"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-xl px-4 py-3"
                style="display: none;"
            >
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="selected.length + ' selected'"></span>
                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>

                <select
                    @change="if ($event.target.value) { applyBulk('status', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set status…</option>
                    @foreach(\App\Support\Enums\ActionItemStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>

                <select
                    @change="if ($event.target.value) { applyBulk('priority', $event.target.value); $event.target.value = ''; }"
                    class="text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                    <option value="">Set priority…</option>
                    @foreach(\App\Support\Enums\ActionItemPriority::cases() as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    @click="applyBulk('delete')"
                    class="text-sm font-medium px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                >
                    Delete
                </button>

                <div class="h-4 w-px bg-gray-200 dark:bg-slate-600"></div>
                <button type="button" @click="selected = []" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>

{{-- Slide-over --}}
<x-action-items.slide-over />
@endsection
```

### Step 3: Run all tests

Run: `php artisan test --compact`
Expected: All tests pass (787+ passing)

### Step 4: Run pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 5: Run npm build

Run: `npm run build`
Expected: Build completes without errors

### Step 6: Commit

```bash
git add resources/views/action-items/index.blade.php \
        app/Domain/ActionItem/Controllers/ActionItemController.php
git commit -m "feat: add bulk actions, slide-over, view toggle, and kanban to per-meeting action items"
```
