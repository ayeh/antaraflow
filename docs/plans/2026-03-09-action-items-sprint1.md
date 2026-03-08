# Action Items Sprint 1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add inline status toggle (floating mini-panel + comment), quick complete checkbox, and server-side filter bar to the Action Items dashboard and per-meeting list.

**Architecture:** Alpine.js handles UI interactions and AJAX. A new `ActionItemStatusController` exposes a `PATCH` endpoint consumed by the frontend. `ActionItemService::getDashboard()` is extended with filter params. Blade components are created for the reusable status badge (with popover) and the filter bar.

**Tech Stack:** Laravel 12, Alpine.js, Tailwind CSS, Blade components, Pest 4

**Design doc:** `docs/plans/2026-03-09-action-items-sprint1-design.md`

---

## Task 1: Enrich ActionItemStatus enum with label() and colorClass()

**Files:**
- Modify: `app/Support/Enums/ActionItemStatus.php`

**Step 1: Add methods to the enum**

Replace the entire file contents with:

```php
<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case CarriedForward = 'carried_forward';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::CarriedForward => 'Carried Forward',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            self::InProgress => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            self::Cancelled => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300',
            self::CarriedForward => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        };
    }
}
```

**Step 2: Run pint**

```bash
vendor/bin/pint app/Support/Enums/ActionItemStatus.php --format agent
```

**Step 3: Commit**

```bash
git add app/Support/Enums/ActionItemStatus.php
git commit -m "feat: add label() and colorClass() methods to ActionItemStatus enum"
```

---

## Task 2: Enrich ActionItemPriority enum with label() and colorClass()

**Files:**
- Modify: `app/Support/Enums/ActionItemPriority.php`

**Step 1: Add methods to the enum**

Replace the entire file contents with:

```php
<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300',
            self::Medium => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            self::High => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
            self::Critical => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        };
    }
}
```

**Step 2: Run pint**

```bash
vendor/bin/pint app/Support/Enums/ActionItemPriority.php --format agent
```

**Step 3: Commit**

```bash
git add app/Support/Enums/ActionItemPriority.php
git commit -m "feat: add label() and colorClass() methods to ActionItemPriority enum"
```

---

## Task 3: Create UpdateActionItemStatusRequest

**Files:**
- Create: `app/Domain/ActionItem/Requests/UpdateActionItemStatusRequest.php`

**Step 1: Create the form request**

```bash
php artisan make:request --no-interaction UpdateActionItemStatusRequest
```

Then move it to `app/Domain/ActionItem/Requests/` and replace contents with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActionItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ActionItemStatus::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'A status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is not valid.',
            'comment.max' => 'The note cannot exceed 500 characters.',
        ];
    }
}
```

**Step 2: Run pint**

```bash
vendor/bin/pint app/Domain/ActionItem/Requests/UpdateActionItemStatusRequest.php --format agent
```

---

## Task 4: Write failing tests for ActionItemStatusController

**Files:**
- Create: `tests/Feature/Domain/ActionItem/Controllers/ActionItemStatusControllerTest.php`

**Step 1: Create the test file**

```bash
php artisan make:test --no-interaction --pest Domain/ActionItem/Controllers/ActionItemStatusControllerTest
```

**Step 2: Replace file contents with tests**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Models\ActionItemHistory;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
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
    $this->item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);
});

test('user can update action item status via patch endpoint', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'status', 'status_label', 'status_color_class', 'completed_at']);

    expect($response->json('status'))->toBe('in_progress')
        ->and($response->json('status_label'))->toBe('In Progress');

    $this->assertDatabaseHas('action_items', [
        'id' => $this->item->id,
        'status' => 'in_progress',
    ]);
});

test('completing a task via status endpoint sets completed_at', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'completed',
        ]);

    $response->assertOk();
    expect($response->json('completed_at'))->not->toBeNull();

    $this->assertDatabaseHas('action_items', [
        'id' => $this->item->id,
        'status' => 'completed',
    ]);
});

test('status change with comment is saved in history', function () {
    $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
            'comment' => 'Starting this now',
        ]);

    $this->assertDatabaseHas('action_item_histories', [
        'action_item_id' => $this->item->id,
        'field_changed' => 'status',
        'old_value' => 'open',
        'new_value' => 'in_progress',
        'comment' => 'Starting this now',
    ]);
});

test('status change without comment creates history with null comment', function () {
    $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
        ]);

    expect(ActionItemHistory::where('action_item_id', $this->item->id)->first()->comment)->toBeNull();
});

test('invalid status returns 422', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'not_a_real_status',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('missing status returns 422', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('guest cannot update action item status', function () {
    $response = $this->patchJson(
        route('meetings.action-items.status', [$this->meeting, $this->item]),
        ['status' => 'in_progress']
    );

    $response->assertUnauthorized();
});
```

**Step 3: Run tests to confirm they fail**

```bash
php artisan test --compact --filter=ActionItemStatusControllerTest
```

Expected: All tests FAIL with `Route [meetings.action-items.status] not defined` or similar.

---

## Task 5: Create ActionItemStatusController and route

**Files:**
- Create: `app/Domain/ActionItem/Controllers/ActionItemStatusController.php`
- Modify: `routes/web.php`

**Step 1: Create the controller**

```bash
php artisan make:class --no-interaction app/Domain/ActionItem/Controllers/ActionItemStatusController
```

Replace file contents with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\UpdateActionItemStatusRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ActionItemStatusController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function update(
        UpdateActionItemStatusRequest $request,
        MinutesOfMeeting $meeting,
        ActionItem $actionItem,
    ): JsonResponse {
        $this->authorize('update', $actionItem);

        $status = ActionItemStatus::from($request->validated('status'));

        $updated = $this->actionItemService->changeStatus(
            $actionItem,
            $status,
            $request->user(),
            $request->validated('comment'),
        );

        return response()->json([
            'id' => $updated->id,
            'status' => $updated->status->value,
            'status_label' => $updated->status->label(),
            'status_color_class' => $updated->status->colorClass(),
            'completed_at' => $updated->completed_at?->toIso8601String(),
        ]);
    }
}
```

**Step 2: Add route to routes/web.php**

Inside the `Route::prefix('meetings/{meeting}')->as('meetings.')->group(...)` block, after the existing `action-items` resource route, add:

```php
Route::patch('action-items/{actionItem}/status', [ActionItemStatusController::class, 'update'])->name('action-items.status');
```

Also add the import at the top of `routes/web.php`:

```php
use App\Domain\ActionItem\Controllers\ActionItemStatusController;
```

**Step 3: Run pint**

```bash
vendor/bin/pint app/Domain/ActionItem/Controllers/ActionItemStatusController.php routes/web.php --format agent
```

**Step 4: Run the tests to verify they pass**

```bash
php artisan test --compact --filter=ActionItemStatusControllerTest
```

Expected: All 7 tests PASS.

**Step 5: Commit**

```bash
git add app/Domain/ActionItem/Controllers/ActionItemStatusController.php \
        app/Domain/ActionItem/Requests/UpdateActionItemStatusRequest.php \
        routes/web.php
git commit -m "feat: add ActionItemStatusController PATCH endpoint for inline status updates"
```

---

## Task 6: Write failing tests for getDashboard() filters

**Files:**
- Modify: `tests/Feature/Domain/ActionItem/Services/ActionItemServiceTest.php`

**Step 1: Append these tests to the existing test file**

```php
test('getDashboard filters by status', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::InProgress,
    ]);

    $results = $service->getDashboard(
        $this->org->id,
        statuses: [ActionItemStatus::Open],
    );

    expect($results)->toHaveCount(1)
        ->and($results->first()->status)->toBe(ActionItemStatus::Open);
});

test('getDashboard filters by priority', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->highPriority()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'priority' => \App\Support\Enums\ActionItemPriority::Low,
    ]);

    $results = $service->getDashboard(
        $this->org->id,
        priorities: [\App\Support\Enums\ActionItemPriority::High],
    );

    expect($results)->toHaveCount(1)
        ->and($results->first()->priority)->toBe(\App\Support\Enums\ActionItemPriority::High);
});

test('getDashboard with no filters excludes cancelled and carried forward', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Cancelled,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::CarriedForward,
    ]);

    $results = $service->getDashboard($this->org->id);

    expect($results)->toHaveCount(1)
        ->and($results->first()->status)->toBe(ActionItemStatus::Open);
});

test('getDashboard with explicit cancelled filter shows cancelled items', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Cancelled,
    ]);

    $results = $service->getDashboard(
        $this->org->id,
        statuses: [ActionItemStatus::Cancelled],
    );

    expect($results)->toHaveCount(1);
});
```

**Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter="getDashboard filters"
```

Expected: Tests FAIL — `getDashboard()` does not accept named `statuses` or `priorities` params yet.

---

## Task 7: Extend ActionItemService::getDashboard() with filter params

**Files:**
- Modify: `app/Domain/ActionItem/Services/ActionItemService.php`

**Step 1: Update the getDashboard() method signature and body**

Replace the existing `getDashboard()` method with:

```php
/**
 * @param  array<int, ActionItemStatus>  $statuses
 * @param  array<int, \App\Support\Enums\ActionItemPriority>  $priorities
 */
public function getDashboard(
    int $organizationId,
    ?int $userId = null,
    array $statuses = [],
    array $priorities = [],
): Collection {
    $query = ActionItem::query()
        ->where('organization_id', $organizationId)
        ->with(['assignedTo', 'meeting', 'createdBy']);

    if ($userId) {
        $query->where('assigned_to', $userId);
    }

    if (! empty($statuses)) {
        $query->whereIn('status', $statuses);
    } else {
        $query->whereNotIn('status', [ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward]);
    }

    if (! empty($priorities)) {
        $query->whereIn('priority', $priorities);
    }

    return $query->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END', [now()])
        ->orderBy('due_date')
        ->orderBy('priority')
        ->get();
}
```

**Step 2: Run pint**

```bash
vendor/bin/pint app/Domain/ActionItem/Services/ActionItemService.php --format agent
```

**Step 3: Run the service tests**

```bash
php artisan test --compact --filter=ActionItemServiceTest
```

Expected: All tests PASS.

**Step 4: Commit**

```bash
git add app/Domain/ActionItem/Services/ActionItemService.php \
        tests/Feature/Domain/ActionItem/Services/ActionItemServiceTest.php
git commit -m "feat: extend ActionItemService::getDashboard() with status and priority filters"
```

---

## Task 8: Extend ActionItemDashboardController with filter params

**Files:**
- Modify: `app/Domain/ActionItem/Controllers/ActionItemDashboardController.php`

**Step 1: Replace the controller with filtered version**

```php
<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ActionItemDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActionItem::class);

        $user = $request->user();

        $selectedStatuses = array_filter(
            array_map(
                fn (string $s) => ActionItemStatus::tryFrom($s),
                (array) $request->query('status', [])
            )
        );

        $selectedPriorities = array_filter(
            array_map(
                fn (string $p) => ActionItemPriority::tryFrom($p),
                (array) $request->query('priority', [])
            )
        );

        $assigneeFilter = $request->query('assignee');
        $assigneeUserId = $assigneeFilter === 'me' ? $user->id : null;

        $actionItems = $this->actionItemService->getDashboard(
            $user->current_organization_id,
            $assigneeUserId,
            array_values($selectedStatuses),
            array_values($selectedPriorities),
        );

        return view('action-items.dashboard', compact(
            'actionItems',
            'selectedStatuses',
            'selectedPriorities',
            'assigneeFilter',
        ));
    }
}
```

**Step 2: Run pint**

```bash
vendor/bin/pint app/Domain/ActionItem/Controllers/ActionItemDashboardController.php --format agent
```

**Step 3: Run existing dashboard controller test**

```bash
php artisan test --compact --filter=ActionItemControllerTest
```

Expected: All tests still PASS (backward compatible — no filters = same behaviour).

**Step 4: Commit**

```bash
git add app/Domain/ActionItem/Controllers/ActionItemDashboardController.php
git commit -m "feat: extend ActionItemDashboardController with status, priority, assignee filter params"
```

---

## Task 9: Create reusable action-item-status-badge Blade component

**Files:**
- Create: `resources/views/components/action-item-status-badge.blade.php`

**Step 1: Create the component file**

```bash
php artisan make:component --no-interaction --view action-item-status-badge
```

**Step 2: Replace file contents**

File: `resources/views/components/action-item-status-badge.blade.php`

```blade
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
            if (newStatus === this.selectedStatus) { this.close(); return; }
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
        class="absolute left-0 top-full mt-1 z-50 w-56 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-lg p-2"
        style="display: none;"
    >
        {{-- Status options --}}
        <template x-for="option in options" :key="option.value">
            <button
                type="button"
                @click="save(option.value)"
                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700/50 text-left"
            >
                <span :class="option.colorClass" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" x-text="option.label"></span>
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
            <div x-show="showComment" x-transition class="px-2 pb-2">
                <textarea
                    x-model="comment"
                    placeholder="Optional note..."
                    rows="2"
                    class="w-full text-xs rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-800 dark:text-gray-200 px-2 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-violet-500"
                ></textarea>
            </div>
        </div>
    </div>
</div>
```

**Step 3: Commit**

```bash
git add resources/views/components/action-item-status-badge.blade.php
git commit -m "feat: add action-item-status-badge Blade component with Alpine.js popover"
```

---

## Task 10: Create action-items filter-bar Blade component

**Files:**
- Create: `resources/views/components/action-items/filter-bar.blade.php`

**Step 1: Create the directory and file**

```bash
mkdir -p resources/views/components/action-items
```

File: `resources/views/components/action-items/filter-bar.blade.php`

```blade
@props(['selectedStatuses' => [], 'selectedPriorities' => [], 'assigneeFilter' => null])

@php
    $statusOptions = \App\Support\Enums\ActionItemStatus::cases();
    $priorityOptions = \App\Support\Enums\ActionItemPriority::cases();

    $activeStatusValues = array_map(fn ($s) => $s instanceof \App\Support\Enums\ActionItemStatus ? $s->value : $s, $selectedStatuses);
    $activePriorityValues = array_map(fn ($p) => $p instanceof \App\Support\Enums\ActionItemPriority ? $p->value : $p, $selectedPriorities);

    $hasActiveFilters = ! empty($activeStatusValues) || ! empty($activePriorityValues) || $assigneeFilter === 'me';
@endphp

<form
    method="GET"
    action="{{ route('action-items.dashboard') }}"
    x-data="{
        statuses: @js($activeStatusValues),
        priorities: @js($activePriorityValues),
        assignee: '{{ $assigneeFilter ?? '' }}',
        toggleStatus(val) {
            this.statuses.includes(val)
                ? this.statuses = this.statuses.filter(s => s !== val)
                : this.statuses.push(val);
        },
        togglePriority(val) {
            this.priorities.includes(val)
                ? this.priorities = this.priorities.filter(p => p !== val)
                : this.priorities.push(val);
        },
    }"
>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <div class="flex flex-wrap items-center gap-4">

            {{-- Status Filter --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</span>
                @foreach ($statusOptions as $status)
                    <button
                        type="button"
                        @click="toggleStatus('{{ $status->value }}')"
                        :class="statuses.includes('{{ $status->value }}')
                            ? '{{ $status->colorClass() }} ring-2 ring-offset-1 ring-violet-500'
                            : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                    >
                        {{ $status->label() }}
                    </button>
                    <input type="hidden" name="status[]" :value="'{{ $status->value }}'" x-show="statuses.includes('{{ $status->value }}')">
                @endforeach
            </div>

            <div class="h-5 w-px bg-gray-200 dark:bg-slate-600 hidden sm:block"></div>

            {{-- Priority Filter --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</span>
                @foreach ($priorityOptions as $priority)
                    <button
                        type="button"
                        @click="togglePriority('{{ $priority->value }}')"
                        :class="priorities.includes('{{ $priority->value }}')
                            ? '{{ $priority->colorClass() }} ring-2 ring-offset-1 ring-violet-500'
                            : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                    >
                        {{ $priority->label() }}
                    </button>
                    <input type="hidden" name="priority[]" :value="'{{ $priority->value }}'" x-show="priorities.includes('{{ $priority->value }}')">
                @endforeach
            </div>

            <div class="h-5 w-px bg-gray-200 dark:bg-slate-600 hidden sm:block"></div>

            {{-- Assignee Filter --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</span>
                <button
                    type="button"
                    @click="assignee = assignee === 'me' ? '' : 'me'"
                    :class="assignee === 'me' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300 ring-2 ring-offset-1 ring-violet-500' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all cursor-pointer"
                >
                    Assigned to me
                </button>
                <input type="hidden" name="assignee" :value="assignee" x-show="assignee === 'me'">
            </div>

            {{-- Actions --}}
            <div class="ml-auto flex items-center gap-3">
                @if ($hasActiveFilters)
                    <a href="{{ route('action-items.dashboard') }}" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        Clear all
                    </a>
                @endif
                <button
                    type="submit"
                    class="bg-violet-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-violet-700 transition-colors"
                >
                    Apply
                </button>
            </div>

        </div>
    </div>
</form>
```

**Step 2: Commit**

```bash
git add resources/views/components/action-items/filter-bar.blade.php
git commit -m "feat: add action-items filter-bar Blade component"
```

---

## Task 11: Update action-items dashboard.blade.php

**Files:**
- Modify: `resources/views/action-items/dashboard.blade.php`

**Step 1: Replace file with updated version**

```blade
@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Action Items</h1>
    </div>

    {{-- Filter Bar --}}
    <x-action-items.filter-bar
        :selectedStatuses="$selectedStatuses"
        :selectedPriorities="$selectedPriorities"
        :assigneeFilter="$assigneeFilter"
    />

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
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
                            x-data="{ completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }} }"
                            :class="completed ? 'opacity-60' : ''"
                        >
                            {{-- Quick Complete Checkbox --}}
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    :checked="completed"
                                    @change="
                                        completed = !completed;
                                        fetch('{{ route('meetings.action-items.status', [$item->meeting, $item]) }}', {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({ status: completed ? 'completed' : 'open' }),
                                        }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                    "
                                    class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                >
                            </td>

                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.action-items.show', [$item->meeting, $item]) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->title }}</a>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $item->meeting) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400">{{ $item->meeting->title }}</a>
                            </td>

                            {{-- Inline Status Badge --}}
                            <td class="px-6 py-4">
                                <x-action-item-status-badge :item="$item" :meeting="$item->meeting" />
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->priority->colorClass() }}">
                                    {{ $item->priority->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->assignedTo?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $item->due_date?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
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
</div>
@endsection
```

**Step 2: Commit**

```bash
git add resources/views/action-items/dashboard.blade.php
git commit -m "feat: update action items dashboard with filter bar, inline status toggle, and quick complete"
```

---

## Task 12: Update per-meeting action-items index.blade.php

**Files:**
- Modify: `resources/views/action-items/index.blade.php`

**Step 1: Replace file with updated version**

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
        <a href="{{ route('meetings.action-items.create', $meeting) }}" class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Action Item
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assignee</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @forelse($actionItems as $item)
                        <tr
                            class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
                            x-data="{ completed: {{ $item->status === \App\Support\Enums\ActionItemStatus::Completed ? 'true' : 'false' }} }"
                            :class="completed ? 'opacity-60' : ''"
                        >
                            {{-- Quick Complete Checkbox --}}
                            <td class="px-4 py-4">
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
                                        }).catch(() => { completed = !completed; alert('Failed to update. Please try again.'); })
                                    "
                                    class="w-4 h-4 rounded border-gray-300 text-violet-600 cursor-pointer focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-700"
                                >
                            </td>

                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.action-items.show', [$meeting, $item]) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-400">{{ $item->title }}</a>
                            </td>

                            {{-- Inline Status Badge --}}
                            <td class="px-6 py-4">
                                <x-action-item-status-badge :item="$item" :meeting="$meeting" />
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->priority->colorClass() }}">
                                    {{ $item->priority->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->assignedTo?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm {{ $item->due_date?->isPast() && $item->status !== \App\Support\Enums\ActionItemStatus::Completed ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $item->due_date?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('meetings.action-items.edit', [$meeting, $item]) }}" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 font-medium">Edit</a>
                            </td>
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
</div>
@endsection
```

**Step 2: Commit**

```bash
git add resources/views/action-items/index.blade.php
git commit -m "feat: update per-meeting action items list with inline status toggle and quick complete"
```

---

## Task 13: Run pint and full test suite

**Step 1: Run pint across all modified files**

```bash
vendor/bin/pint --dirty --format agent
```

Fix any reported issues, then stage and amend or commit if needed.

**Step 2: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests PASS. No regressions.

**Step 3: Final commit if pint made changes**

```bash
git add -A
git commit -m "style: apply pint formatting across Sprint 1 files"
```
