# antaraFlow — Remaining Features Sprint Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build all remaining unimplemented features across 4 sprints, from quick-wins to complex integrations.

**Architecture:** Domain-driven with controllers/services/requests/views per feature. All features follow existing patterns: `BelongsToOrganization` trait, `AuthorizesRequests`, Form Requests, AuditService logging, Tailwind+Alpine views.

**Tech Stack:** Laravel 12, Pest 4, Tailwind CSS 4, Alpine.js, existing domain structure

---

## Sprint Overview

| Sprint | Features | Tasks | Effort |
|--------|----------|-------|--------|
| **Sprint 1** | Tags, Version History UI, Attendee Groups | 1–9 | Quick wins — models exist |
| **Sprint 2** | AI Provider Config UI, API Keys, Audit Log Viewer | 10–16 | Admin/settings pages |
| **Sprint 3** | Guest Access Portal, Notifications UI, Usage Tracking | 17–22 | User-facing features |
| **Sprint 4** | Subscription/Billing UI, Public REST API | 23–28 | Complex, revenue-critical |

---

## ✅ Sprint 1: Quick Wins (Models Already Exist)

---

### Task 1: Tags — Backend (Controller, Policy, Requests, Routes)

**Files:**
- Create: `app/Domain/Meeting/Controllers/MomTagController.php`
- Create: `app/Domain/Meeting/Policies/MomTagPolicy.php`
- Create: `app/Domain/Meeting/Requests/CreateMomTagRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create MomTagPolicy**

```php
<?php
// app/Domain/Meeting/Policies/MomTagPolicy.php
declare(strict_types=1);

namespace App\Domain\Meeting\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Meeting\Models\MomTag;
use App\Models\User;

class MomTagPolicy
{
    public function __construct(private AuthorizationService $authService) {}

    public function viewAny(User $user): bool
    {
        return $this->authService->hasPermission($user, 'view_meeting');
    }

    public function create(User $user): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }

    public function update(User $user, MomTag $tag): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }

    public function delete(User $user, MomTag $tag): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }
}
```

**Step 2: Create CreateMomTagRequest**

```php
<?php
// app/Domain/Meeting/Requests/CreateMomTagRequest.php
declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMomTagRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $orgId = $this->user()->current_organization_id;
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('mom_tags')->where('organization_id', $orgId)->ignore($this->route('momTag'))],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A tag with this name already exists.',
            'color.regex' => 'Color must be a valid hex color (e.g. #A855F7).',
        ];
    }
}
```

**Step 3: Create MomTagController**

```php
<?php
// app/Domain/Meeting/Controllers/MomTagController.php
declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Requests\CreateMomTagRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MomTagController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', MomTag::class);

        $tags = MomTag::query()->withCount('meetings')->orderBy('name')->get();

        return view('tags.index', compact('tags'));
    }

    public function store(CreateMomTagRequest $request): RedirectResponse
    {
        $this->authorize('create', MomTag::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

        MomTag::query()->create($data);

        return redirect()->route('tags.index')->with('success', 'Tag created.');
    }

    public function update(CreateMomTagRequest $request, MomTag $momTag): RedirectResponse
    {
        $this->authorize('update', $momTag);

        $data = $request->validated();
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        $momTag->update($data);

        return redirect()->route('tags.index')->with('success', 'Tag updated.');
    }

    public function destroy(MomTag $momTag): RedirectResponse
    {
        $this->authorize('delete', $momTag);

        $momTag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag deleted.');
    }
}
```

**Step 4: Add routes to `routes/web.php`**

```php
// Tags
Route::get('tags', [\App\Domain\Meeting\Controllers\MomTagController::class, 'index'])->name('tags.index');
Route::post('tags', [\App\Domain\Meeting\Controllers\MomTagController::class, 'store'])->name('tags.store');
Route::put('tags/{momTag}', [\App\Domain\Meeting\Controllers\MomTagController::class, 'update'])->name('tags.update');
Route::delete('tags/{momTag}', [\App\Domain\Meeting\Controllers\MomTagController::class, 'destroy'])->name('tags.destroy');
```

**Step 5: Register policy in `AppServiceProvider`**

```php
use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Policies\MomTagPolicy;
// in boot():
Gate::policy(MomTag::class, MomTagPolicy::class);
```

**Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add tag controller, policy, requests, and routes"
```

---

### Task 2: Tags — Views

**Files:**
- Create: `resources/views/tags/index.blade.php`
- Modify: `resources/views/meetings/create.blade.php` (add tag selector)
- Modify: `resources/views/meetings/edit.blade.php` (add tag selector)
- Modify: `resources/views/meetings/index.blade.php` (add tag filter)
- Modify: `app/Domain/Meeting/Requests/CreateMeetingRequest.php` (add tags validation)
- Modify: `app/Domain/Meeting/Requests/UpdateMeetingRequest.php` (add tags validation)
- Modify: `app/Domain/Meeting/Services/MeetingService.php` (sync tags on create/update)

**Step 1: Create tags index view**

`resources/views/tags/index.blade.php` — two-panel layout:
- Left: table of existing tags (color swatch, name, meeting count, edit inline, delete)
- Right: "Create Tag" form (name input + color picker with preset swatches)

Use Alpine.js for inline edit toggle.

```blade
@extends('layouts.app')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Tags</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Tag List --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @forelse($tags as $tag)
                <div class="flex items-center justify-between px-5 py-3" x-data="{ editing: false }">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $tag->color }}"></span>
                        <span class="text-sm font-medium text-gray-900" x-show="!editing">{{ $tag->name }}</span>
                        <form method="POST" action="{{ route('tags.update', $tag) }}" x-show="editing" @click.outside="editing = false">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $tag->name }}" class="text-sm border border-gray-300 rounded px-2 py-1 w-32">
                            <input type="hidden" name="color" value="{{ $tag->color }}">
                            <button type="submit" class="text-xs text-violet-600 ml-1">Save</button>
                        </form>
                        <span class="text-xs text-gray-400">{{ $tag->meetings_count }} meetings</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="editing = !editing" class="text-xs text-gray-400 hover:text-gray-600">Edit</button>
                        <form method="POST" action="{{ route('tags.destroy', $tag) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600" onclick="return confirm('Delete this tag?')">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-gray-400">No tags yet.</div>
            @endforelse
        </div>

        {{-- Create Tag Form --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4 self-start">
            <h2 class="text-sm font-semibold text-gray-700">Create Tag</h2>
            <form method="POST" action="{{ route('tags.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="50" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="e.g. Strategy">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Color</label>
                    <div class="flex gap-2 flex-wrap">
                        @foreach(['#A855F7','#3B82F6','#10B981','#F59E0B','#EF4444','#EC4899','#06B6D4','#6366F1'] as $color)
                            <label class="cursor-pointer">
                                <input type="radio" name="color" value="{{ $color }}" class="sr-only" {{ old('color', '#A855F7') === $color ? 'checked' : '' }}>
                                <span class="block w-6 h-6 rounded-full border-2 border-transparent checked:border-gray-900" style="background-color: {{ $color }}"></span>
                            </label>
                        @endforeach
                    </div>
                    @error('color')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full bg-violet-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-violet-700 transition-colors">Create Tag</button>
            </form>
        </div>
    </div>
</div>
@endsection
```

**Step 2: Add `tags` validation + sync to meeting requests & service**

In `CreateMeetingRequest` and `UpdateMeetingRequest`, add:
```php
'tags' => ['nullable', 'array'],
'tags.*' => ['integer', 'exists:mom_tags,id'],
```

In `MeetingService::create()` and `MeetingService::update()`, after saving `$mom`, sync tags:
```php
if (isset($data['tags'])) {
    $mom->tags()->sync($data['tags']);
}
```

**Step 3: Add tag multi-select to meeting create/edit views**

In both `meetings/create.blade.php` and `meetings/edit.blade.php`, add a tag selector section:
```blade
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
    <div class="flex flex-wrap gap-2">
        @foreach($availableTags as $tag)
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                    {{ in_array($tag->id, old('tags', $meeting->tags->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full" style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $tag->color }}"></span>
                    {{ $tag->name }}
                </span>
            </label>
        @endforeach
    </div>
</div>
```

Pass `$availableTags = MomTag::query()->orderBy('name')->get()` from both `create()` and `edit()` controller methods.

**Step 4: Add tag filter to meetings index**

In `meetings/index.blade.php`, add tag filter chips above the meetings list. Pass `$availableTags` from `MeetingController::index()` and filter via `MeetingSearchService`.

**Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add tag views and meeting tag selector"
```

---

### Task 3: Tags — Tests

**Files:**
- Create: `tests/Feature/Domain/Meeting/Controllers/MomTagControllerTest.php`

**Step 1: Create test**

Run: `php artisan make:test --pest Domain/Meeting/Controllers/MomTagControllerTest --no-interaction`

```php
<?php
use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MomTag;
use App\Models\User;
use App\Support\Enums\UserRole;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->users()->attach($this->user->id, ['role' => UserRole::Owner->value]);
});

test('admin can view tags index', function () {
    $tag = MomTag::factory()->for($this->org)->create();

    $response = $this->actingAs($this->user)->get(route('tags.index'));

    $response->assertOk()->assertSee($tag->name);
});

test('admin can create tag', function () {
    $response = $this->actingAs($this->user)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseHas('mom_tags', ['name' => 'Strategy', 'organization_id' => $this->org->id]);
});

test('cannot create duplicate tag name in same org', function () {
    MomTag::factory()->for($this->org)->create(['name' => 'Strategy']);

    $response = $this->actingAs($this->user)->post(route('tags.store'), [
        'name' => 'Strategy',
        'color' => '#A855F7',
    ]);

    $response->assertSessionHasErrors('name');
});

test('admin can update tag', function () {
    $tag = MomTag::factory()->for($this->org)->create();

    $response = $this->actingAs($this->user)->put(route('tags.update', $tag), [
        'name' => 'Updated',
        'color' => '#3B82F6',
    ]);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseHas('mom_tags', ['id' => $tag->id, 'name' => 'Updated']);
});

test('admin can delete tag', function () {
    $tag = MomTag::factory()->for($this->org)->create();

    $response = $this->actingAs($this->user)->delete(route('tags.destroy', $tag));

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseMissing('mom_tags', ['id' => $tag->id]);
});
```

**Step 2: Run tests**

Run: `php artisan test --compact --filter=MomTagController`
Expected: All PASS

**Step 3: Commit**

```bash
git add tests/
git commit -m "test: add MomTag controller tests"
```

---

### Task 4: Version History UI — Controller & Routes

**Files:**
- Create: `app/Domain/Meeting/Controllers/MomVersionController.php`
- Modify: `routes/web.php`

**Step 1: Create MomVersionController**

```php
<?php
// app/Domain/Meeting/Controllers/MomVersionController.php
declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Domain\Meeting\Services\VersionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MomVersionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private VersionService $versionService) {}

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $versions = $this->versionService->getVersionHistory($meeting);

        return view('meetings.versions.index', compact('meeting', 'versions'));
    }

    public function show(MinutesOfMeeting $meeting, MomVersion $version): View
    {
        $this->authorize('view', $meeting);

        return view('meetings.versions.show', compact('meeting', 'version'));
    }

    public function restore(MinutesOfMeeting $meeting, MomVersion $version): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $this->versionService->restoreVersion($meeting, $version, request()->user());

        return redirect()->route('meetings.show', $meeting)
            ->with('success', "Restored to version {$version->version_number}.");
    }
}
```

**Step 2: Add routes to `routes/web.php`** (inside the meetings group)

```php
Route::get('meetings/{meeting}/versions', [\App\Domain\Meeting\Controllers\MomVersionController::class, 'index'])->name('meetings.versions.index');
Route::get('meetings/{meeting}/versions/{version}', [\App\Domain\Meeting\Controllers\MomVersionController::class, 'show'])->name('meetings.versions.show');
Route::post('meetings/{meeting}/versions/{version}/restore', [\App\Domain\Meeting\Controllers\MomVersionController::class, 'restore'])->name('meetings.versions.restore');
```

**Step 3: Commit**

```bash
git add -A
git commit -m "feat: add version history controller and routes"
```

---

### Task 5: Version History UI — Views & Tests

**Files:**
- Create: `resources/views/meetings/versions/index.blade.php`
- Create: `resources/views/meetings/versions/show.blade.php`
- Modify: `resources/views/meetings/show.blade.php` (add History tab)
- Create: `tests/Feature/Domain/Meeting/Controllers/MomVersionControllerTest.php`

**Step 1: Create versions index view**

Timeline list showing: version number (badge), change summary, who created it, when (relative time). "View" button per row. "Restore" button for versions older than current. Current version marked with a "Current" badge.

**Step 2: Create version show view**

Show full snapshot content (title, summary, content as formatted HTML). Diff-style display comparing snapshot to current (use simple before/after panels). "Restore this version" button with confirmation.

**Step 3: Add "History" tab to meeting show**

In `meetings/show.blade.php`, add a "History" tab that links to `route('meetings.versions.index', $meeting)`.

**Step 4: Create tests**

Run: `php artisan make:test --pest Domain/Meeting/Controllers/MomVersionControllerTest --no-interaction`

```php
test('can view version history for meeting', function () { ... });
test('can view specific version', function () { ... });
test('owner can restore version', function () { ... });
test('viewer cannot restore version', function () { ... });
```

**Step 5: Run tests**

Run: `php artisan test --compact --filter=MomVersionController`

**Step 6: Run Pint & Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add version history views and tests"
```

---

### Task 6: Attendee Groups — Backend

**Files:**
- Create: `app/Domain/Attendee/Controllers/AttendeeGroupController.php`
- Create: `app/Domain/Attendee/Policies/AttendeeGroupPolicy.php`
- Create: `app/Domain/Attendee/Requests/CreateAttendeeGroupRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create AttendeeGroupPolicy**

```php
<?php
// app/Domain/Attendee/Policies/AttendeeGroupPolicy.php
declare(strict_types=1);

namespace App\Domain\Attendee\Policies;

use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Models\User;

class AttendeeGroupPolicy
{
    public function __construct(private AuthorizationService $authService) {}

    public function viewAny(User $user): bool
    {
        return $this->authService->hasPermission($user, 'view_meeting');
    }

    public function create(User $user): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }

    public function update(User $user, AttendeeGroup $group): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }

    public function delete(User $user, AttendeeGroup $group): bool
    {
        return $this->authService->hasPermission($user, 'manage_templates');
    }
}
```

**Step 2: Create CreateAttendeeGroupRequest**

```php
<?php
// app/Domain/Attendee/Requests/CreateAttendeeGroupRequest.php
declare(strict_types=1);

namespace App\Domain\Attendee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAttendeeGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_members' => ['nullable', 'array'],
            'default_members.*.name' => ['required', 'string', 'max:255'],
            'default_members.*.email' => ['required', 'email'],
            'default_members.*.role' => ['nullable', 'string'],
        ];
    }
}
```

**Step 3: Create AttendeeGroupController**

```php
<?php
// app/Domain/Attendee/Controllers/AttendeeGroupController.php
declare(strict_types=1);

namespace App\Domain\Attendee\Controllers;

use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Requests\CreateAttendeeGroupRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AttendeeGroupController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', AttendeeGroup::class);

        $groups = AttendeeGroup::query()->latest()->get();

        return view('attendee-groups.index', compact('groups'));
    }

    public function create(): View
    {
        $this->authorize('create', AttendeeGroup::class);
        return view('attendee-groups.create');
    }

    public function store(CreateAttendeeGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', AttendeeGroup::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;

        AttendeeGroup::query()->create($data);

        return redirect()->route('attendee-groups.index')->with('success', 'Group created.');
    }

    public function edit(AttendeeGroup $attendeeGroup): View
    {
        $this->authorize('update', $attendeeGroup);
        return view('attendee-groups.edit', compact('attendeeGroup'));
    }

    public function update(CreateAttendeeGroupRequest $request, AttendeeGroup $attendeeGroup): RedirectResponse
    {
        $this->authorize('update', $attendeeGroup);

        $attendeeGroup->update($request->validated());

        return redirect()->route('attendee-groups.index')->with('success', 'Group updated.');
    }

    public function destroy(AttendeeGroup $attendeeGroup): RedirectResponse
    {
        $this->authorize('delete', $attendeeGroup);

        $attendeeGroup->delete();

        return redirect()->route('attendee-groups.index')->with('success', 'Group deleted.');
    }
}
```

**Step 4: Add routes**

```php
Route::resource('attendee-groups', \App\Domain\Attendee\Controllers\AttendeeGroupController::class);
```

**Step 5: Register policy & Run Pint**

```php
// AppServiceProvider
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Policies\AttendeeGroupPolicy;
Gate::policy(AttendeeGroup::class, AttendeeGroupPolicy::class);
```

Run: `vendor/bin/pint --dirty --format agent`

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add attendee group backend"
```

---

### Task 7: Attendee Groups — Views & Tests

**Files:**
- Create: `resources/views/attendee-groups/index.blade.php`
- Create: `resources/views/attendee-groups/create.blade.php`
- Create: `resources/views/attendee-groups/edit.blade.php`
- Modify: `resources/views/meetings/tabs/attendees.blade.php` (add "Use Group" button)
- Create: `tests/Feature/Domain/Attendee/Controllers/AttendeeGroupControllerTest.php`

**Step 1: Create index view**

Cards listing each group with name, description, member count (from `default_members` JSON array count), "Edit" and "Delete" buttons.

**Step 2: Create create/edit views**

Form with name, description, and a dynamic member list builder (Alpine.js — "Add Member" button adds a row with name, email, role inputs; "Remove" button per row). Members stored as JSON.

```blade
<div x-data="{ members: {{ json_encode(old('default_members', $attendeeGroup->default_members ?? [])) }} }">
    <template x-for="(member, index) in members" :key="index">
        <div class="flex gap-2 items-start mb-2">
            <input type="text" :name="`default_members[${index}][name]`" x-model="member.name" placeholder="Name" class="...">
            <input type="email" :name="`default_members[${index}][email]`" x-model="member.email" placeholder="Email" class="...">
            <button type="button" @click="members.splice(index, 1)" class="text-red-400">Remove</button>
        </div>
    </template>
    <button type="button" @click="members.push({name:'',email:'',role:''})" class="text-sm text-violet-600">+ Add Member</button>
</div>
```

**Step 3: Add "Use Group" to attendee tab**

In `meetings/tabs/attendees.blade.php`, add a "Use Group" dropdown that sends a POST request to bulk-import group members.

**Step 4: Create tests**

```php
test('admin can list attendee groups', function () { ... });
test('admin can create attendee group', function () { ... });
test('admin can update attendee group', function () { ... });
test('admin can delete attendee group', function () { ... });
```

**Step 5: Run tests & commit**

```bash
php artisan test --compact --filter=AttendeeGroupController
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add attendee group views and tests"
```

---

## ✅ Sprint 2: Admin / Settings Pages

---

### Task 8: Audit Log Viewer — Controller, View & Tests

**Files:**
- Create: `app/Domain/Account/Controllers/AuditLogController.php`
- Modify: `routes/web.php`
- Create: `resources/views/audit-log/index.blade.php`
- Create: `tests/Feature/Domain/Account/Controllers/AuditLogControllerTest.php`

**Step 1: Create AuditLogController**

```php
<?php
// app/Domain/Account/Controllers/AuditLogController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $this->authService->hasPermission($user, 'manage_organization'),
            403,
            'Only admins can view audit logs.'
        );

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(50);

        $actions = AuditLog::query()->distinct()->pluck('action')->sort()->values();
        $orgUsers = \App\Models\User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->orderBy('name')->get(['id', 'name']);

        return view('audit-log.index', compact('logs', 'actions', 'orgUsers'));
    }
}
```

**Step 2: Add route**

```php
Route::get('audit-log', [\App\Domain\Account\Controllers\AuditLogController::class, 'index'])->name('audit-log.index');
```

**Step 3: Create audit log view**

Filter bar (action dropdown, user dropdown, date range) + paginated table: Date, User, Action, Resource Type, Resource ID, IP Address. Expandable row to show old/new values as JSON diff.

**Step 4: Create tests**

```php
test('org admin can view audit log', function () { ... });
test('viewer cannot view audit log', function () { ... });
test('audit log filters by action', function () { ... });
```

**Step 5: Run tests, Pint & commit**

```bash
php artisan test --compact --filter=AuditLogController
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add audit log viewer"
```

---

### Task 9: AI Provider Config — Controller, Requests & Routes

**Files:**
- Create: `app/Domain/Account/Controllers/AiProviderConfigController.php`
- Create: `app/Domain/Account/Requests/CreateAiProviderConfigRequest.php`
- Create: `app/Domain/Account/Requests/UpdateAiProviderConfigRequest.php`
- Modify: `routes/web.php`

**Step 1: Create CreateAiProviderConfigRequest**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAiProviderConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:openai,anthropic,google,ollama'],
            'display_name' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
```

**Step 2: Create AiProviderConfigController**

```php
<?php
// app/Domain/Account/Controllers/AiProviderConfigController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Requests\CreateAiProviderConfigRequest;
use App\Domain\Account\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class AiProviderConfigController extends Controller
{
    public function __construct(private AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $configs = AiProviderConfig::query()->orderBy('provider')->get();

        return view('ai-provider-configs.index', compact('configs'));
    }

    public function create(Request $request): View
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);
        return view('ai-provider-configs.create');
    }

    public function store(CreateAiProviderConfigRequest $request): RedirectResponse
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;

        if (! empty($data['api_key'])) {
            $data['api_key_encrypted'] = Crypt::encryptString($data['api_key']);
        }
        unset($data['api_key']);

        if ($request->boolean('is_default')) {
            AiProviderConfig::query()->update(['is_default' => false]);
        }

        AiProviderConfig::query()->create($data);

        return redirect()->route('ai-provider-configs.index')->with('success', 'AI provider configured.');
    }

    public function edit(Request $request, AiProviderConfig $aiProviderConfig): View
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);
        return view('ai-provider-configs.edit', compact('aiProviderConfig'));
    }

    public function update(CreateAiProviderConfigRequest $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $data = $request->validated();

        if (! empty($data['api_key'])) {
            $data['api_key_encrypted'] = Crypt::encryptString($data['api_key']);
        }
        unset($data['api_key']);

        if ($request->boolean('is_default')) {
            AiProviderConfig::query()->where('id', '!=', $aiProviderConfig->id)->update(['is_default' => false]);
        }

        $aiProviderConfig->update($data);

        return redirect()->route('ai-provider-configs.index')->with('success', 'AI provider updated.');
    }

    public function destroy(Request $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $aiProviderConfig->delete();

        return redirect()->route('ai-provider-configs.index')->with('success', 'AI provider removed.');
    }
}
```

**Step 3: Add routes**

```php
Route::resource('ai-provider-configs', \App\Domain\Account\Controllers\AiProviderConfigController::class);
```

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add AI provider config controller and routes"
```

---

### Task 10: AI Provider Config — Views & Tests

**Files:**
- Create: `resources/views/ai-provider-configs/index.blade.php`
- Create: `resources/views/ai-provider-configs/create.blade.php`
- Create: `resources/views/ai-provider-configs/edit.blade.php`
- Create: `tests/Feature/Domain/Account/Controllers/AiProviderConfigControllerTest.php`

**Step 1: Create index view**

Cards per configured provider with: provider logo/icon, display name, model name, active badge, default badge, "Edit" / "Delete" buttons. "Add Provider" button top-right.

**Step 2: Create create/edit views**

Form with: provider select (OpenAI / Anthropic / Google / Ollama), display name, API key (password input, masked — show only last 4 chars in edit mode), model input, base URL (optional, for Ollama), is_active toggle, is_default radio.

**Step 3: Create tests**

```php
test('org owner can view AI provider configs', function () { ... });
test('org owner can create AI provider config', function () { ... });
test('creating default config clears other defaults', function () { ... });
test('member cannot manage AI providers', function () { ... });
```

**Step 4: Run tests, Pint & commit**

```bash
php artisan test --compact --filter=AiProviderConfigController
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add AI provider config views and tests"
```

---

### Task 11: API Keys — Controller, Views & Tests

**Files:**
- Create: `app/Domain/Account/Controllers/ApiKeyController.php`
- Create: `app/Domain/Account/Requests/CreateApiKeyRequest.php`
- Modify: `routes/web.php`
- Create: `resources/views/api-keys/index.blade.php`
- Create: `tests/Feature/Domain/Account/Controllers/ApiKeyControllerTest.php`

**Step 1: Create CreateApiKeyRequest**

```php
<?php
declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'in:read,write,delete'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
```

**Step 2: Create ApiKeyController**

Key logic: on `store`, generate a random 32-char key, hash it for storage, show the raw key ONCE in the flash message.

```php
<?php
// app/Domain/Account/Controllers/ApiKeyController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Requests\CreateApiKeyRequest;
use App\Domain\Account\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function __construct(private AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $apiKeys = ApiKey::query()->latest()->get();

        return view('api-keys.index', compact('apiKeys'));
    }

    public function store(CreateApiKeyRequest $request): RedirectResponse
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $rawKey = Str::random(32);
        $prefix = Str::random(8);
        $fullKey = "af_{$prefix}_{$rawKey}";

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;
        $data['key'] = substr($fullKey, 0, 32); // store prefix + partial
        $data['secret_hash'] = hash('sha256', $fullKey);
        $data['is_active'] = true;

        ApiKey::query()->create($data);

        return redirect()->route('api-keys.index')
            ->with('api_key_created', $fullKey)
            ->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $apiKey->delete();

        return redirect()->route('api-keys.index')->with('success', 'API key revoked.');
    }
}
```

**Step 3: Add routes**

```php
Route::get('api-keys', [\App\Domain\Account\Controllers\ApiKeyController::class, 'index'])->name('api-keys.index');
Route::post('api-keys', [\App\Domain\Account\Controllers\ApiKeyController::class, 'store'])->name('api-keys.store');
Route::delete('api-keys/{apiKey}', [\App\Domain\Account\Controllers\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
```

**Step 4: Create index view**

- Alert box at top if `session('api_key_created')` — show the key with copy-to-clipboard button (Alpine.js)
- "Create API Key" form (name, permissions checkboxes, optional expiry date)
- Table: name, key prefix (show first 8 chars + `...`), permissions, expires, last used, status toggle, revoke button

**Step 5: Create tests**

```php
test('owner can view API keys', function () { ... });
test('owner can create API key', function () { ... });
test('owner can revoke API key', function () { ... });
test('member cannot manage API keys', function () { ... });
```

**Step 6: Run tests, Pint & commit**

```bash
php artisan test --compact --filter=ApiKeyController
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add API keys management"
```

---

### Task 12: Navigation — Add Sprint 2 Links

**Files:**
- Modify: `resources/views/layouts/partials/flyout-panel.blade.php`

**Step 1: Add Settings links**

In the Settings section of the flyout panel, add:
- **AI Providers** → `route('ai-provider-configs.index')`
- **API Keys** → `route('api-keys.index')`
- **Audit Log** → `route('audit-log.index')`
- **Tags** → `route('tags.index')`
- **Attendee Groups** → `route('attendee-groups.index')`

**Step 2: Commit**

```bash
git add resources/views/layouts/
git commit -m "feat: add admin settings links to navigation"
```

---

## ✅ Sprint 3: User-Facing Features

---

### Task 13: Guest Access Portal

**Files:**
- Create: `app/Domain/Collaboration/Controllers/GuestAccessController.php`
- Modify: `routes/web.php` (outside auth middleware)
- Create: `resources/views/guest/meeting-view.blade.php`
- Modify: `resources/views/collaboration/share-panel.blade.php` (display share link)

**Step 1: Create GuestAccessController**

```php
<?php
// app/Domain/Collaboration/Controllers/GuestAccessController.php
declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\MeetingShare;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class GuestAccessController extends Controller
{
    public function show(string $token, Request $request): View
    {
        $share = MeetingShare::query()
            ->with(['meeting.attendees.user', 'meeting.actionItems.assignedTo', 'meeting.createdBy'])
            ->where('share_token', $token)
            ->whereNull('shared_with_user_id') // guest link (not user-specific share)
            ->firstOrFail();

        abort_if($share->isExpired(), 410, 'This share link has expired.');

        $meeting = $share->meeting;

        return view('guest.meeting-view', compact('meeting', 'share'));
    }
}
```

**Step 2: Add guest route (outside auth middleware)**

In `routes/web.php`, OUTSIDE the `auth` middleware group:
```php
Route::get('share/{token}', [\App\Domain\Collaboration\Controllers\GuestAccessController::class, 'show'])->name('guest.meeting');
```

**Step 3: Create guest view**

Standalone view (`@extends('layouts.guest')`) showing meeting details in read-only mode: title, date, status, attendees, summary, content, action items table. No edit buttons. Header shows "Shared View" notice with expiry date.

**Step 4: Update share panel to display copy-able link**

In `collaboration/share-panel.blade.php`, when a guest link is generated, show the full URL (`route('guest.meeting', $share->share_token)`) with copy-to-clipboard button (Alpine.js).

**Step 5: Create tests**

```php
test('guest can view meeting with valid share token', function () { ... });
test('expired share token returns 410', function () { ... });
test('invalid share token returns 404', function () { ... });
```

**Step 6: Run tests, Pint & commit**

```bash
php artisan test --compact --filter=GuestAccessController
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add guest access portal for shared meeting links"
```

---

### Task 14: Notifications — Service & Controller

**Files:**
- Create: `app/Domain/Account/Services/NotificationService.php`
- Create: `app/Domain/Account/Controllers/NotificationController.php`
- Modify: `routes/web.php`

**Step 1: Create NotificationService**

```php
<?php
// app/Domain/Account/Services/NotificationService.php
declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function getUnread(User $user): Collection
    {
        return $user->unreadNotifications()->latest()->limit(20)->get();
    }

    public function getAll(User $user): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate(30);
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $user->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
```

**Step 2: Create NotificationController**

```php
<?php
// app/Domain/Account/Controllers/NotificationController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request): View
    {
        $notifications = $this->notificationService->getAll($request->user());
        return view('notifications.index', compact('notifications'));
    }

    public function unread(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUnread($request->user());
        $count = $this->notificationService->getUnreadCount($request->user());
        return response()->json(['notifications' => $notifications, 'count' => $count]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $this->notificationService->markAsRead($request->user(), $id);
        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->notificationService->markAllAsRead($request->user());
        return back()->with('success', 'All notifications marked as read.');
    }
}
```

**Step 3: Add routes**

```php
Route::get('notifications', [\App\Domain\Account\Controllers\NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread', [\App\Domain\Account\Controllers\NotificationController::class, 'unread'])->name('notifications.unread');
Route::post('notifications/{id}/read', [\App\Domain\Account\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
Route::post('notifications/read-all', [\App\Domain\Account\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
```

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add notification service and controller"
```

---

### Task 15: Notifications — Views & Bell Icon

**Files:**
- Create: `resources/views/notifications/index.blade.php`
- Modify: `resources/views/layouts/partials/header.blade.php` (add bell icon with badge)

**Step 1: Create notifications index view**

List of all notifications, grouped by "Today" / "This Week" / "Older". Each row: icon (based on notification type), message, relative timestamp, "Mark read" link, read/unread styling. "Mark All Read" button at top.

**Step 2: Add bell icon to header**

In `layouts/partials/header.blade.php`, add a bell button that:
- Shows unread count badge (red dot if > 0)
- On click, opens a dropdown (Alpine.js) with last 5 unread notifications fetched via `/notifications/unread` JSON endpoint
- "View all" link at bottom of dropdown

```blade
<div x-data="{ open: false, count: 0, items: [] }"
     x-init="fetch('{{ route('notifications.unread') }}').then(r=>r.json()).then(d=>{ count=d.count; items=d.notifications; })">
    <button @click="open=!open" class="relative p-2 text-gray-500 hover:text-gray-700">
        {{-- Bell SVG --}}
        <span x-show="count > 0" x-text="count"
              class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"></span>
    </button>
    <div x-show="open" @click.outside="open=false" class="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-gray-200 shadow-lg z-50">
        <!-- notification items -->
    </div>
</div>
```

**Step 3: Commit**

```bash
git add -A
git commit -m "feat: add notifications view and bell icon"
```

---

### Task 16: Usage Tracking Dashboard

**Files:**
- Create: `app/Domain/Account/Controllers/UsageController.php`
- Modify: `routes/web.php`
- Create: `resources/views/usage/index.blade.php`

**Step 1: Create UsageController**

```php
<?php
// app/Domain/Account/Controllers/UsageController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UsageTracking;
use App\Domain\Account\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class UsageController extends Controller
{
    public function __construct(private AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        abort_unless($this->authService->hasPermission($request->user(), 'manage_organization'), 403);

        $orgId = $request->user()->current_organization_id;
        $currentPeriod = now()->format('Y-m');

        $usage = UsageTracking::query()
            ->where('organization_id', $orgId)
            ->where('period', $currentPeriod)
            ->get()
            ->keyBy('metric');

        $history = UsageTracking::query()
            ->where('organization_id', $orgId)
            ->orderByDesc('period')
            ->limit(12)
            ->get()
            ->groupBy('metric');

        $subscription = \App\Domain\Account\Models\OrganizationSubscription::query()
            ->with('subscriptionPlan')
            ->where('organization_id', $orgId)
            ->latest()
            ->first();

        return view('usage.index', compact('usage', 'history', 'subscription', 'currentPeriod'));
    }
}
```

**Step 2: Add route**

```php
Route::get('usage', [\App\Domain\Account\Controllers\UsageController::class, 'index'])->name('usage.index');
```

**Step 3: Create usage view**

Progress bars for current period usage vs plan limits (meetings, audio minutes, storage). History table showing past 12 months. Link to subscription upgrade.

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add usage tracking dashboard"
```

---

## ✅ Sprint 4: Revenue & Scale Features

---

### Task 17: Subscription Plans — Management Page

**Files:**
- Create: `app/Domain/Account/Controllers/SubscriptionController.php`
- Modify: `routes/web.php`
- Create: `resources/views/subscription/index.blade.php`

**Step 1: Create SubscriptionController**

```php
<?php
// app/Domain/Account/Controllers/SubscriptionController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $orgId = $request->user()->current_organization_id;

        $currentSubscription = OrganizationSubscription::query()
            ->with('subscriptionPlan')
            ->where('organization_id', $orgId)
            ->latest()
            ->first();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('subscription.index', compact('currentSubscription', 'plans'));
    }
}
```

**Step 2: Add route**

```php
Route::get('subscription', [\App\Domain\Account\Controllers\SubscriptionController::class, 'index'])->name('subscription.index');
```

**Step 3: Create subscription view**

Pricing grid (Free / Pro / Business / Enterprise) with feature comparison table. Current plan highlighted. "Upgrade" / "Contact Sales" buttons. Note: actual payment processing is Phase 4 (Stripe integration).

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add subscription plans page"
```

---

### Task 18: Public REST API — Foundation

> **Note:** This is a Phase 4 feature. See separate plan when ready.

Placeholder routing + auth skeleton only:

**Files:**
- Create: `routes/api.php` update with versioned prefix
- Create: `app/Domain/API/Controllers/V1/MeetingApiController.php` (stub)
- Create: `app/Domain/API/Middleware/ApiKeyAuthentication.php`

**Scope:** API key middleware that validates `Authorization: Bearer <key>` against hashed `api_keys.secret_hash`. Stub GET `/api/v1/meetings` endpoint returning paginated list.

---

### Task 19: Run Final Pint & Full Test Suite

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All PASS

**Step 3: Fix any failures**

**Step 4: Commit**

```bash
git add -A
git commit -m "chore: final pint formatting and test suite verification"
```

---

## Feature Checklist

### Sprint 1 — Quick Wins
- [ ] **Task 1** — Tags Backend (Controller, Policy, Requests, Routes)
- [ ] **Task 2** — Tags Views (index, meeting selector, filter)
- [ ] **Task 3** — Tags Tests
- [ ] **Task 4** — Version History Controller & Routes
- [ ] **Task 5** — Version History Views & Tests
- [ ] **Task 6** — Attendee Groups Backend
- [ ] **Task 7** — Attendee Groups Views & Tests

### Sprint 2 — Admin/Settings
- [ ] **Task 8** — Audit Log Viewer
- [ ] **Task 9** — AI Provider Config Backend
- [ ] **Task 10** — AI Provider Config Views & Tests
- [ ] **Task 11** — API Keys Management
- [ ] **Task 12** — Navigation — Sprint 2 Links

### Sprint 3 — User-Facing
- [ ] **Task 13** — Guest Access Portal
- [ ] **Task 14** — Notifications Service & Controller
- [ ] **Task 15** — Notifications Views & Bell Icon
- [ ] **Task 16** — Usage Tracking Dashboard

### Sprint 4 — Revenue & Scale
- [ ] **Task 17** — Subscription Plans Page
- [ ] **Task 18** — Public REST API Foundation
- [ ] **Task 19** — Final Pint & Test Suite

---

## Future Phases (Not In This Plan)

| Feature | Notes |
|---------|-------|
| Stripe billing integration | Phase 4 — requires Stripe webhook handling |
| Zoom / Slack / Teams integration | Phase 4 — OAuth + webhook setup |
| Self-hosted Docker + Ollama | Phase 3 — separate deployment project |
| QR code attendance registration | Phase 3 — uses `mom_join_settings` |
| Email distribution for MOM | Phase 3 — uses `mom_email_distributions` |
| Advanced AI (cross-meeting search, coaching) | Phase 3 — requires Scout + Meilisearch |
