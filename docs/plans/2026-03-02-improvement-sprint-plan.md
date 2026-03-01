# Improvement Sprint Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Production-readiness improvements across 5 areas: performance, API completeness, UI polish, test coverage, and cPanel deployment.

**Architecture:** Iterative improvements to existing Laravel 12 app. No new domains. Each area is independent and can be implemented in any order.

**Tech Stack:** Laravel 12, PHP 8.4, Pest v4, Tailwind CSS, GitHub Actions, bash

---

## Task 1: Fix N+1 Queries in DashboardController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php` (if view accesses unloaded relations)

**Step 1: Check what the dashboard view accesses on upcomingActions**

```bash
grep -n "action\." resources/views/dashboard.blade.php | head -30
```

Look for: `$action->meeting`, `$action->assignedTo`, `$action->meeting->title`, etc.

**Step 2: Add eager loading to upcomingActions and upcomingMeetings**

In `DashboardController::index()`, update `$upcomingActions` query:

```php
$upcomingActions = ActionItem::query()
    ->where('organization_id', $orgId)
    ->where('assigned_to', $user->id)
    ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
    ->with(['meeting'])  // ADD THIS
    ->orderBy('due_date')
    ->take(5)
    ->get();
```

Update `$upcomingMeetings` query:

```php
$upcomingMeetings = MinutesOfMeeting::query()
    ->where('organization_id', $orgId)
    ->where('meeting_date', '>=', now()->startOfDay())
    ->with(['createdBy'])  // ADD THIS if view uses it
    ->orderBy('meeting_date')
    ->limit(5)
    ->get();
```

**Step 3: Check MeetingController index via SearchService**

```bash
cat app/Domain/Meeting/Services/MeetingSearchService.php
# or grep -r "SearchService" app/Domain/Meeting/
```

If `searchService->search()` returns meetings without eager loading `createdBy`, add `->with('createdBy')` in the search service's return query.

**Step 4: Run tests to verify nothing broke**

```bash
php artisan test --compact
```

Expected: all 329 tests pass (or more if SearchService fix triggers existing tests).

**Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p
git commit -m "perf: add eager loading to dashboard and meeting index queries"
```

---

## Task 2: Fix N+1 in ActionItemService::getDashboard

**Files:**
- Modify: `app/Domain/ActionItem/Services/ActionItemService.php`

**Step 1: Read the getDashboard method**

```bash
grep -A 30 "getDashboard" app/Domain/ActionItem/Services/ActionItemService.php
```

**Step 2: Add eager loading**

If `getDashboard` returns action items, ensure the query includes:

```php
->with(['meeting', 'assignedTo', 'createdBy'])
```

**Step 3: Check the dashboard view for any other N+1**

```bash
grep -n "\->" resources/views/action-items/dashboard.blade.php | grep -v "^\s*{{" | head -20
```

**Step 4: Run tests and commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git commit -am "perf: eager load relations in ActionItemService::getDashboard"
```

---

## Task 3: API — Add Store/Update/Destroy to MeetingApiController

**Files:**
- Create: `app/Domain/API/Requests/V1/StoreApiMeetingRequest.php`
- Create: `app/Domain/API/Requests/V1/UpdateApiMeetingRequest.php`
- Modify: `app/Domain/API/Controllers/V1/MeetingApiController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Domain/API/MeetingApiTest.php`

**Step 1: Create StoreApiMeetingRequest**

```php
<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
        ];
    }
}
```

**Step 2: Create UpdateApiMeetingRequest**

```php
<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'meeting_date' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

**Step 3: Add store(), update(), destroy() to MeetingApiController**

```php
use App\Domain\API\Requests\V1\StoreApiMeetingRequest;
use App\Domain\API\Requests\V1\UpdateApiMeetingRequest;
use App\Support\Enums\MeetingStatus;

public function store(StoreApiMeetingRequest $request): JsonResponse
{
    $data = $request->validated();
    $data['organization_id'] = $this->organizationId($request);
    $data['created_by'] = $request->attributes->get('api_key')->organization_id; // use org as proxy
    $data['status'] = MeetingStatus::Draft;

    // Note: created_by needs a user. API key doesn't have a user.
    // Use the organization's owner or set to null if nullable.
    // Check if MinutesOfMeeting.created_by is nullable in migration.
    // If NOT nullable, store the organization_id and handle accordingly.
    // SIMPLEST: make created_by the first owner of the org.
    $org = \App\Domain\Account\Models\Organization::find($this->organizationId($request));
    $owner = $org->members()->wherePivot('role', 'owner')->first();
    $data['created_by'] = $owner?->id ?? $org->members()->first()->id;

    $meeting = MinutesOfMeeting::query()->create($data);

    return response()->json(new MeetingResource($meeting), 201);
}

public function update(UpdateApiMeetingRequest $request, int $id): JsonResponse
{
    $meeting = MinutesOfMeeting::query()
        ->where('organization_id', $this->organizationId($request))
        ->where('id', $id)
        ->firstOrFail();

    $meeting->update($request->validated());

    return response()->json(new MeetingResource($meeting->fresh()));
}

public function destroy(Request $request, int $id): JsonResponse
{
    $meeting = MinutesOfMeeting::query()
        ->where('organization_id', $this->organizationId($request))
        ->where('id', $id)
        ->firstOrFail();

    $meeting->delete();

    return response()->json(null, 204);
}
```

**⚠️ IMPORTANT NOTE on `created_by`:** Check if `minutes_of_meetings.created_by` is nullable:

```bash
php artisan tinker --execute="echo Schema::getColumnType('minutes_of_meetings', 'created_by');"
# or
grep -A 2 "created_by" database/migrations/*create_minutes_of_meetings*
```

If NOT nullable, the approach above (finding org owner) is correct. If nullable, just omit `created_by` from API creates.

**Step 4: Update routes/api.php**

```php
Route::prefix('v1')->middleware(\App\Domain\API\Middleware\ApiKeyAuthentication::class)->group(function () {
    Route::get('meetings', [MeetingApiController::class, 'index']);
    Route::post('meetings', [MeetingApiController::class, 'store']);
    Route::get('meetings/{id}', [MeetingApiController::class, 'show']);
    Route::patch('meetings/{id}', [MeetingApiController::class, 'update']);
    Route::delete('meetings/{id}', [MeetingApiController::class, 'destroy']);
    Route::get('action-items', [ActionItemApiController::class, 'index']);
});
```

**Step 5: Add tests to MeetingApiTest.php**

```php
it('POST /api/v1/meetings creates a meeting', function () {
    $response = $this->postJson('/api/v1/meetings', [
        'title' => 'API Created Meeting',
        'meeting_date' => '2026-03-15',
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('title', 'API Created Meeting')
        ->assertJsonPath('status', 'draft');

    $this->assertDatabaseHas('minutes_of_meetings', [
        'title' => 'API Created Meeting',
        'organization_id' => $this->org->id,
    ]);
});

it('POST /api/v1/meetings validates required fields', function () {
    $this->postJson('/api/v1/meetings', [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('PATCH /api/v1/meetings/{id} updates a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $this->patchJson("/api/v1/meetings/{$meeting->id}", [
        'title' => 'Updated Title',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('title', 'Updated Title');
});

it('PATCH /api/v1/meetings/{id} returns 404 for wrong org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create();

    $this->patchJson("/api/v1/meetings/{$otherMeeting->id}", [
        'title' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});

it('DELETE /api/v1/meetings/{id} deletes a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $this->deleteJson("/api/v1/meetings/{$meeting->id}", [], $this->headers)
        ->assertNoContent();

    $this->assertDatabaseMissing('minutes_of_meetings', ['id' => $meeting->id, 'deleted_at' => null]);
});

it('DELETE /api/v1/meetings/{id} returns 404 for wrong org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create();

    $this->deleteJson("/api/v1/meetings/{$otherMeeting->id}", [], $this->headers)
        ->assertNotFound();
});
```

**Step 6: Run tests**

```bash
php artisan test --compact --filter=MeetingApi
```

Expected: all meeting API tests pass.

**Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/API/ routes/api.php tests/Feature/Domain/API/MeetingApiTest.php
git commit -m "feat(api): add store, update, destroy endpoints for meetings"
```

---

## Task 4: API — Add Store/Update to ActionItemApiController

**Files:**
- Create: `app/Domain/API/Requests/V1/StoreApiActionItemRequest.php`
- Create: `app/Domain/API/Requests/V1/UpdateApiActionItemRequest.php`
- Modify: `app/Domain/API/Controllers/V1/ActionItemApiController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Domain/API/ActionItemApiTest.php`

**Step 1: Create StoreApiActionItemRequest**

```php
<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use App\Support\Enums\ActionItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'minutes_of_meeting_id' => ['required', 'integer'],
            'priority' => ['nullable', Rule::enum(ActionItemPriority::class)],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
```

**Step 2: Create UpdateApiActionItemRequest**

```php
<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'nullable', Rule::enum(ActionItemPriority::class)],
            'status' => ['sometimes', Rule::enum(ActionItemStatus::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
```

**Step 3: Add store() and update() to ActionItemApiController**

```php
use App\Domain\API\Requests\V1\StoreApiActionItemRequest;
use App\Domain\API\Requests\V1\UpdateApiActionItemRequest;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;

public function store(StoreApiActionItemRequest $request): JsonResponse
{
    $orgId = $this->organizationId($request);
    $data = $request->validated();

    // Verify meeting belongs to this org
    $meetingExists = MinutesOfMeeting::query()
        ->where('organization_id', $orgId)
        ->where('id', $data['minutes_of_meeting_id'])
        ->exists();

    if (! $meetingExists) {
        return response()->json([
            'message' => 'Meeting not found or does not belong to this organization.',
        ], 422);
    }

    $data['organization_id'] = $orgId;
    $data['status'] = ActionItemStatus::Open;
    $data['priority'] = ActionItemPriority::tryFrom($data['priority'] ?? '') ?? ActionItemPriority::Medium;

    // Set created_by: find org owner
    $org = \App\Domain\Account\Models\Organization::find($orgId);
    $data['created_by'] = $org->members()->wherePivot('role', 'owner')->first()?->id
        ?? $org->members()->first()->id;

    $actionItem = ActionItem::query()->create($data);

    return response()->json(new ActionItemResource($actionItem), 201);
}

public function update(UpdateApiActionItemRequest $request, int $id): JsonResponse
{
    $actionItem = ActionItem::query()
        ->where('organization_id', $this->organizationId($request))
        ->where('id', $id)
        ->firstOrFail();

    $actionItem->update($request->validated());

    return response()->json(new ActionItemResource($actionItem->fresh()));
}
```

**Step 4: Update routes/api.php**

```php
Route::get('action-items', [ActionItemApiController::class, 'index']);
Route::post('action-items', [ActionItemApiController::class, 'store']);
Route::patch('action-items/{id}', [ActionItemApiController::class, 'update']);
```

**Step 5: Add tests to ActionItemApiTest.php**

```php
it('POST /api/v1/action-items creates an action item', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $response = $this->postJson('/api/v1/action-items', [
        'title' => 'API Action Item',
        'minutes_of_meeting_id' => $meeting->id,
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('title', 'API Action Item')
        ->assertJsonPath('status', 'open');

    $this->assertDatabaseHas('action_items', [
        'title' => 'API Action Item',
        'organization_id' => $this->org->id,
    ]);
});

it('POST /api/v1/action-items rejects meeting from different org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->postJson('/api/v1/action-items', [
        'title' => 'Hack',
        'minutes_of_meeting_id' => $otherMeeting->id,
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Meeting not found'));
});

it('PATCH /api/v1/action-items/{id} updates an action item', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    $actionItem = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $this->patchJson("/api/v1/action-items/{$actionItem->id}", [
        'title' => 'Updated',
        'status' => 'completed',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('title', 'Updated')
        ->assertJsonPath('status', 'completed');
});

it('PATCH /api/v1/action-items/{id} returns 404 for wrong org', function () {
    $actionItem = ActionItem::factory()->create(); // different org

    $this->patchJson("/api/v1/action-items/{$actionItem->id}", [
        'title' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});
```

**Step 6: Run tests and commit**

```bash
php artisan test --compact --filter=ActionItemApi
vendor/bin/pint --dirty --format agent
git add app/Domain/API/ routes/api.php tests/Feature/Domain/API/ActionItemApiTest.php
git commit -m "feat(api): add store and update endpoints for action items"
```

---

## Task 5: UI — Empty States

**Files:** Multiple Blade views. Pattern is the same for each.

**Step 1: Read the empty state pattern from an existing view**

Check if any view already has a nice empty state:

```bash
grep -rn "empty\|No.*found\|haven't\|yet" resources/views/ --include="*.blade.php" -l
```

**Step 2: Add empty state to meetings/index.blade.php**

Find the `@forelse` or `@if($meetings->isEmpty())` block. If using `@foreach`, change to `@forelse`:

```blade
@forelse ($meetings as $meeting)
    {{-- existing meeting row/card --}}
@empty
    <div class="text-center py-16">
        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No meetings yet</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first meeting.</p>
        <div class="mt-6">
            <a href="{{ route('meetings.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                New Meeting
            </a>
        </div>
    </div>
@endforelse
```

**Step 3: Apply same pattern to these views**

Apply empty state (with appropriate icon, text, CTA) to:
- `resources/views/action-items/dashboard.blade.php` — "No action items yet"
- `resources/views/tags/index.blade.php` — "No tags yet"
- `resources/views/meeting-templates/index.blade.php` — "No templates yet"
- `resources/views/meeting-series/index.blade.php` — "No meeting series yet"
- `resources/views/notifications/index.blade.php` — "You're all caught up!" (no CTA)
- `resources/views/audit-log/index.blade.php` — "No audit log entries"
- `resources/views/api-keys/index.blade.php` — "No API keys yet"
- `resources/views/ai-provider-configs/index.blade.php` — "No AI providers configured"

**Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/
git commit -m "ui: add empty states to all list/index views"
```

---

## Task 6: UI — Dark Mode Audit

**Files:** Multiple Blade views.

**Step 1: Find views with potential dark mode gaps**

```bash
# Find hardcoded colors without dark: variant
grep -rn "text-gray-900\|bg-white\|border-gray-200\|text-gray-700" resources/views/ --include="*.blade.php" | grep -v "dark:" | head -30
```

**Step 2: Fix patterns**

Common fixes:
- `text-gray-900` → `text-gray-900 dark:text-white`
- `bg-white` → `bg-white dark:bg-slate-800`
- `border-gray-200` → `border-gray-200 dark:border-slate-700`
- `text-gray-700` → `text-gray-700 dark:text-gray-300`
- `text-gray-500` → `text-gray-500 dark:text-gray-400`

Focus on recently added views: `transcriptions/show.blade.php`, `notifications/index.blade.php`, `usage/index.blade.php`, `subscription/index.blade.php`, `guest/meeting-view.blade.php`.

**Step 3: Commit**

```bash
git add resources/views/
git commit -m "ui: fix dark mode color inconsistencies across views"
```

---

## Task 7: UI — Mobile Responsiveness

**Files:** Multiple Blade views.

**Step 1: Find tables without overflow wrapper**

```bash
grep -rn "<table" resources/views/ --include="*.blade.php" -l | xargs grep -L "overflow-x-auto"
```

**Step 2: Wrap tables in overflow container**

For each table that lacks it:

```blade
<div class="overflow-x-auto">
    <table class="min-w-full ...">
        ...
    </table>
</div>
```

**Step 3: Fix action button stacking on small screens**

Replace rigid `flex` rows with responsive versions:

```blade
{{-- Before --}}
<div class="flex items-center gap-3">

{{-- After --}}
<div class="flex flex-wrap items-center gap-3">
```

**Step 4: Run visual check and commit**

```bash
git add resources/views/
git commit -m "ui: improve mobile responsiveness for tables and action buttons"
```

---

## Task 8: Quality — Edge Case Tests

**Files:**
- Create: `tests/Feature/Domain/Meeting/MeetingWorkflowTest.php`
- Modify: `tests/Feature/Domain/API/MeetingApiTest.php` (auth edge cases)
- Modify: `tests/Feature/Domain/Account/Controllers/ApiKeyControllerTest.php` (if needed)

**Step 1: Create MeetingWorkflowTest.php**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('cannot edit an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::Approved,
    ]);

    $response = $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Changed',
    ]);

    $response->assertRedirect(); // redirected back with error
    $this->assertDatabaseHas('minutes_of_meetings', [
        'id' => $meeting->id,
        'status' => MeetingStatus::Approved->value,
    ]);
});

test('can only finalize a draft or in-progress meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::Approved,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.finalize', $meeting))
        ->assertSessionHasErrors();
});

test('approved meeting can be reverted to draft from finalized', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::Finalized,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.revert', $meeting))
        ->assertRedirect();

    $this->assertDatabaseHas('minutes_of_meetings', [
        'id' => $meeting->id,
        'status' => MeetingStatus::Draft->value,
    ]);
});
```

**Step 2: Add expired API key test**

In `tests/Feature/Domain/API/MeetingApiTest.php`, add:

```php
it('GET /api/v1/meetings rejects expired API key', function () {
    $rawToken = 'expired-key-' . uniqid();
    ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => true,
        'expires_at' => now()->subDay(), // expired yesterday
    ]);

    $this->getJson('/api/v1/meetings', ['Authorization' => 'Bearer ' . $rawToken])
        ->assertUnauthorized();
});

it('GET /api/v1/meetings rejects inactive API key', function () {
    $rawToken = 'inactive-key-' . uniqid();
    ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => false,
        'expires_at' => null,
    ]);

    $this->getJson('/api/v1/meetings', ['Authorization' => 'Bearer ' . $rawToken])
        ->assertUnauthorized();
});
```

**Step 3: Run tests**

```bash
php artisan test --compact --filter="MeetingWorkflow|MeetingApi"
```

**Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/
git commit -m "test: add edge case tests for meeting workflow and API key auth"
```

---

## Task 9: Quality — Unit Tests for Services

**Files:**
- Create: `tests/Unit/Domain/Meeting/MeetingServiceTest.php`
- Create: `tests/Unit/Domain/ActionItem/ActionItemServiceTest.php`

**Step 1: Create MeetingServiceTest.php**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Services\AuditService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Domain\Meeting\Services\VersionService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MeetingService(
        Mockery::mock(VersionService::class),
        Mockery::mock(AuditService::class)->shouldIgnoreMissing(),
    );
});

test('MeetingService::create sets status to Draft', function () {
    $user = User::factory()->create(['current_organization_id' => 1]);
    $org = \App\Domain\Account\Models\Organization::factory()->create(['id' => 1]);
    $org->members()->attach($user);

    $meeting = $this->service->create(['title' => 'Test'], $user);

    expect($meeting->status)->toBe(MeetingStatus::Draft);
});

test('MeetingService::update throws on approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['status' => MeetingStatus::Approved]);

    expect(fn () => $this->service->update($meeting, ['title' => 'Changed']))
        ->toThrow(\DomainException::class, 'Cannot edit an approved meeting');
});
```

**Note:** Unit tests for service methods that touch the DB still need `RefreshDatabase`. Mock only external services (AuditService, VersionService).

**Step 2: Run and commit**

```bash
php artisan test --compact --filter=MeetingServiceTest
vendor/bin/pint --dirty --format agent
git commit -am "test: add unit tests for MeetingService and ActionItemService"
```

---

## Task 10: Deployment — deploy.sh

**Files:**
- Create: `deploy.sh`
- Create: `.github/workflows/deploy.yml`
- Modify: `.env.example`
- Create: `.htaccess` (at repo root, for cPanel subdomain redirect)

**Step 1: Create deploy.sh**

```bash
#!/usr/bin/env bash

set -euo pipefail

echo "🚀 Starting deployment..."

# Pull latest code
git pull origin main

# Install PHP dependencies (production only)
composer install --no-dev --optimize-autoloader --no-interaction

# Install JS dependencies and build assets
npm ci
npm run build

# Run database migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache 2>/dev/null || true  # ignore if icons package not installed

# Restart queue workers (if using database queue)
php artisan queue:restart

echo "✅ Deployment complete!"
```

```bash
chmod +x deploy.sh
```

**Step 2: Create .github/workflows/deploy.yml**

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          port: ${{ secrets.SSH_PORT || 22 }}
          script: |
            cd ${{ secrets.DEPLOY_PATH }}
            bash deploy.sh
```

**Required GitHub secrets to configure:**
- `SSH_HOST` — server IP or hostname
- `SSH_USER` — SSH username (cPanel user)
- `SSH_KEY` — private SSH key (add public key to server's authorized_keys)
- `SSH_PORT` — SSH port (usually 22)
- `DEPLOY_PATH` — full path to app on server (e.g., `/home/username/app.domain.com`)

**Step 3: Create root .htaccess for cPanel**

cPanel sites typically point to the domain's public folder. If you must have the project root as document root, redirect to `/public`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**Better approach:** In cPanel, point the domain/subdomain document root directly to `public/` folder. No .htaccess needed at root.

**Step 4: Update .env.example**

```bash
cat .env.example
```

Add any missing variables:
```dotenv
# Queue
QUEUE_CONNECTION=database

# Mail (cPanel often uses sendmail or SMTP)
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# AI Providers
AI_DEFAULT_PROVIDER=openai
OPENAI_API_KEY=
ANTHROPIC_API_KEY=

# Storage
FILESYSTEM_DISK=local
```

**Step 5: Add cPanel cron note to README or deploy.sh comment**

Add to deploy.sh as a comment:

```bash
# CPANEL CRON SETUP (one-time):
# In cPanel > Cron Jobs, add:
# * * * * * /usr/local/bin/php /home/USERNAME/DEPLOY_PATH/artisan schedule:run >> /dev/null 2>&1
```

**Step 6: Commit**

```bash
git add deploy.sh .github/ .env.example .htaccess
git commit -m "chore: add deployment scripts for cPanel/DirectAdmin hosting"
```

---

## Final: Run Full Test Suite

After all tasks complete:

```bash
php artisan test --compact
```

Expected: 400+ tests, 0 failures.

```bash
git log --oneline -15
```

Verify all commits are clean and descriptive.
