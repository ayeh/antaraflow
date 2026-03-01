# Complete All Unbuilt & Incomplete Features - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build all remaining unimplemented features: User Profile, Org Settings, Meeting Templates, Meeting Series, Export, Collaboration, Analytics, and Enhanced Dashboard.

**Architecture:** Domain-driven with controllers/services/requests/views per feature. All features use existing patterns: `BelongsToOrganization` trait, `AuthorizesRequests`, Form Requests, AuditService logging, Tailwind+Alpine views.

**Tech Stack:** Laravel 12, Pest 4, Tailwind CSS 4, Alpine.js, Chart.js (CDN), barryvdh/laravel-dompdf, phpoffice/phpword

---

## Task 1: Install Dependencies

**Files:**
- Modify: `composer.json`

**Step 1: Install DomPDF and PhpWord packages**

Run:
```bash
composer require barryvdh/laravel-dompdf phpoffice/phpword --no-interaction
```

**Step 2: Verify installation**

Run: `composer show barryvdh/laravel-dompdf && composer show phpoffice/phpword`
Expected: Package info displayed

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: install dompdf and phpword for export features"
```

---

## Task 2: Create New Enums & Migrations

**Files:**
- Create: `app/Support/Enums/SharePermission.php`
- Modify: `app/Support/Enums/ExportFormat.php` (add Csv)
- Create: `database/migrations/XXXX_create_meeting_shares_table.php`
- Create: `database/migrations/XXXX_create_comments_table.php`

**Step 1: Create SharePermission enum**

```php
<?php
// app/Support/Enums/SharePermission.php
declare(strict_types=1);

namespace App\Support\Enums;

enum SharePermission: string
{
    case View = 'view';
    case Comment = 'comment';
    case Edit = 'edit';
}
```

**Step 2: Update ExportFormat enum to add Csv**

In `app/Support/Enums/ExportFormat.php`, add:
```php
case Csv = 'csv';
```

**Step 3: Create meeting_shares migration**

Run: `php artisan make:migration create_meeting_shares_table --no-interaction`

Content:
```php
Schema::create('meeting_shares', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->cascadeOnDelete();
    $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('permission')->default('view');
    $table->string('share_token', 64)->nullable()->unique();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['minutes_of_meeting_id', 'shared_with_user_id']);
});
```

**Step 4: Create comments migration**

Run: `php artisan make:migration create_comments_table --no-interaction`

Content:
```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->morphs('commentable');
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('body');
    $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

**Step 5: Run migrations**

Run: `php artisan migrate --no-interaction`

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add enums and migrations for collaboration (shares + comments)"
```

---

## Task 3: Create Collaboration Models & Factories

**Files:**
- Create: `app/Domain/Collaboration/Models/MeetingShare.php`
- Create: `app/Domain/Collaboration/Models/Comment.php`
- Create: `database/factories/MeetingShareFactory.php`
- Create: `database/factories/CommentFactory.php`

**Step 1: Create MeetingShare model**

```php
<?php
// app/Domain/Collaboration/Models/MeetingShare.php
declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingShare extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\MeetingShareFactory
    {
        return \Database\Factories\MeetingShareFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
```

**Step 2: Create Comment model**

```php
<?php
// app/Domain/Collaboration/Models/Comment.php
declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected static function newFactory(): \Database\Factories\CommentFactory
    {
        return \Database\Factories\CommentFactory::new();
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
```

**Step 3: Create MeetingShareFactory**

Run: `php artisan make:factory MeetingShareFactory --no-interaction`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MeetingShare> */
class MeetingShareFactory extends Factory
{
    protected $model = MeetingShare::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'shared_with_user_id' => User::factory(),
            'shared_by_user_id' => User::factory(),
            'permission' => fake()->randomElement(SharePermission::cases()),
            'share_token' => Str::random(64),
        ];
    }

    public function guestLink(): static
    {
        return $this->state([
            'shared_with_user_id' => null,
            'share_token' => Str::random(64),
        ]);
    }
}
```

**Step 4: Create CommentFactory**

Run: `php artisan make:factory CommentFactory --no-interaction`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'commentable_type' => MinutesOfMeeting::class,
            'commentable_id' => MinutesOfMeeting::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    public function reply(Comment $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
        ]);
    }
}
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add MeetingShare and Comment models with factories"
```

---

## Task 4: User Profile - Controller, Requests, Service & Routes

**Files:**
- Create: `app/Domain/Account/Controllers/ProfileController.php`
- Create: `app/Domain/Account/Requests/UpdateProfileRequest.php`
- Create: `app/Domain/Account/Requests/UpdatePasswordRequest.php`
- Modify: `routes/web.php`

**Step 1: Create UpdateProfileRequest**

```php
<?php
// app/Domain/Account/Requests/UpdateProfileRequest.php
declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'preferences' => ['nullable', 'array'],
            'preferences.theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
            'preferences.default_meeting_duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'preferences.notifications' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Your name is required.',
            'email.required' => 'Your email address is required.',
            'email.unique' => 'This email address is already in use.',
        ];
    }
}
```

**Step 2: Create UpdatePasswordRequest**

```php
<?php
// app/Domain/Account/Requests/UpdatePasswordRequest.php
declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
```

**Step 3: Create ProfileController**

```php
<?php
// app/Domain/Account/Controllers/ProfileController.php
declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Requests\UpdatePasswordRequest;
use App\Domain\Account\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $preferences = $user->preferences ?? [];
        if (isset($data['preferences'])) {
            $preferences = array_merge($preferences, $data['preferences']);
            unset($data['preferences']);
        }
        $data['preferences'] = $preferences;

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('profile.edit')->with('success', 'Password updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return redirect()->route('profile.edit')->with('success', 'Avatar updated successfully.');
    }
}
```

**Step 4: Add profile routes to `routes/web.php`**

After the dashboard route, add:
```php
// Profile
Route::get('profile', [\App\Domain\Account\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [\App\Domain\Account\Controllers\ProfileController::class, 'update'])->name('profile.update');
Route::put('profile/password', [\App\Domain\Account\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
Route::post('profile/avatar', [\App\Domain\Account\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add user profile controller, requests, and routes"
```

---

## Task 5: User Profile - View

**Files:**
- Create: `resources/views/profile/edit.blade.php`

**Step 1: Create profile edit view**

Full Blade view with 4 sections:
1. **Profile Information** - name, email, phone, timezone, language
2. **Avatar** - upload form with preview
3. **Preferences** - theme (light/dark/system), default meeting duration, notification toggles
4. **Change Password** - current password, new password, confirm

Follow existing view patterns: `@extends('layouts.app')`, dark mode classes, Tailwind styling, `@error` blocks. Use the exact same form input classes as existing views.

**Step 2: Commit**

```bash
git add resources/views/profile/edit.blade.php
git commit -m "feat: add user profile edit view"
```

---

## Task 6: User Profile - Tests

**Files:**
- Create: `tests/Feature/Domain/Account/Controllers/ProfileControllerTest.php`

**Step 1: Create test file**

Run: `php artisan make:test --pest Domain/Account/Controllers/ProfileControllerTest --no-interaction`

Tests to write:
- `test('user can view profile edit page')`
- `test('user can update profile information')`
- `test('user can update password')`
- `test('user cannot update password with wrong current password')`
- `test('user can upload avatar')`
- `test('profile update validates required fields')`

Use same `beforeEach` pattern as MeetingControllerTest: create org, user, attach as owner.

**Step 2: Run tests**

Run: `php artisan test --compact --filter=ProfileController`
Expected: All PASS

**Step 3: Commit**

```bash
git add tests/
git commit -m "test: add profile controller tests"
```

---

## Task 7: Organization Settings Enhancement

**Files:**
- Modify: `app/Domain/Account/Controllers/OrganizationSettingsController.php`
- Create: `app/Domain/Account/Requests/UpdateOrganizationSettingsRequest.php`
- Modify: `resources/views/organizations/settings/edit.blade.php`

**Step 1: Create UpdateOrganizationSettingsRequest**

```php
<?php
// app/Domain/Account/Requests/UpdateOrganizationSettingsRequest.php
declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
        ];
    }
}
```

**Step 2: Update OrganizationSettingsController**

Replace `update` method to use the new form request and update org fields (name, description, timezone, language) plus settings JSON. Add `uploadLogo` method for logo upload.

**Step 3: Enhance organization settings view**

Replace the minimal view with sections:
1. **General** - name, description, timezone, language
2. **Logo** - upload with preview
3. **Members** - list members with roles (read-only display, links to member management)
4. **Subscription** - show current plan (read-only)

**Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: enhance organization settings with proper form fields"
```

---

## Task 8: Meeting Templates - Controller, Service, Requests & Routes

**Files:**
- Create: `app/Domain/Meeting/Controllers/MeetingTemplateController.php`
- Create: `app/Domain/Meeting/Services/MeetingTemplateService.php`
- Create: `app/Domain/Meeting/Requests/CreateMeetingTemplateRequest.php`
- Create: `app/Domain/Meeting/Requests/UpdateMeetingTemplateRequest.php`
- Create: `app/Domain/Meeting/Policies/MeetingTemplatePolicy.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create MeetingTemplatePolicy**

Use `AuthorizationService` with `manage_templates` permission for create/update/delete, `view_meeting` for view.

**Step 2: Create form requests**

CreateMeetingTemplateRequest rules: name (required, max:255), description (nullable), structure (required, array), default_settings (nullable, array), is_shared (boolean).

UpdateMeetingTemplateRequest: same rules as create.

**Step 3: Create MeetingTemplateService**

Methods: `create(data, user)`, `update(template, data)`, `delete(template)`. Use AuditService for logging.

**Step 4: Create MeetingTemplateController**

Standard CRUD controller with `AuthorizesRequests`. Index shows all org templates. Create/store/edit/update/destroy.

**Step 5: Register policy in AppServiceProvider**

Add `Gate::policy(MeetingTemplate::class, MeetingTemplatePolicy::class);`

**Step 6: Add routes**

```php
Route::resource('meeting-templates', \App\Domain\Meeting\Controllers\MeetingTemplateController::class);
```

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add meeting template CRUD controller, service, and routes"
```

---

## Task 9: Meeting Templates - Views

**Files:**
- Create: `resources/views/meeting-templates/index.blade.php`
- Create: `resources/views/meeting-templates/create.blade.php`
- Create: `resources/views/meeting-templates/edit.blade.php`
- Create: `resources/views/meeting-templates/show.blade.php`

**Step 1: Create index view**

List templates as cards with name, description, section count, shared badge, is_default badge. "New Template" button top-right.

**Step 2: Create create/edit views**

Form with: name input, description textarea, structure builder (textarea for JSON for now), default_settings textarea, is_shared toggle, is_default toggle.

**Step 3: Create show view**

Display template details with "Use this template" button that links to `meetings.create?template_id={id}`.

**Step 4: Commit**

```bash
git add resources/views/meeting-templates/
git commit -m "feat: add meeting template views (index, create, edit, show)"
```

---

## Task 10: Meeting Templates - Tests

**Files:**
- Create: `tests/Feature/Domain/Meeting/Controllers/MeetingTemplateControllerTest.php`

**Step 1: Create tests**

Tests: list templates, create template, update template, delete template, viewer cannot create template.

**Step 2: Run tests**

Run: `php artisan test --compact --filter=MeetingTemplateController`

**Step 3: Commit**

```bash
git add tests/
git commit -m "test: add meeting template controller tests"
```

---

## Task 11: Meeting Series - Controller, Service, Requests & Routes

**Files:**
- Create: `app/Domain/Meeting/Controllers/MeetingSeriesController.php`
- Create: `app/Domain/Meeting/Services/MeetingSeriesService.php`
- Create: `app/Domain/Meeting/Requests/CreateMeetingSeriesRequest.php`
- Create: `app/Domain/Meeting/Requests/UpdateMeetingSeriesRequest.php`
- Create: `app/Domain/Meeting/Policies/MeetingSeriesPolicy.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create MeetingSeriesPolicy**

Use `manage_templates` permission (series management follows same permission level).

**Step 2: Create form requests**

CreateMeetingSeriesRequest rules: name (required, max:255), description (nullable), recurrence_pattern (required, in:weekly,biweekly,monthly), recurrence_config (nullable, array), is_active (boolean).

**Step 3: Create MeetingSeriesService**

Methods:
- `create(data, user)` - create series
- `update(series, data)` - update series
- `delete(series)` - soft delete
- `generateUpcoming(series, count)` - create N meetings based on recurrence pattern, linked to series via `meeting_series_id`

**Step 4: Create MeetingSeriesController**

Standard CRUD + `generateMeetings` action.

**Step 5: Register policy, add routes**

```php
Route::resource('meeting-series', \App\Domain\Meeting\Controllers\MeetingSeriesController::class);
Route::post('meeting-series/{meetingSeries}/generate', [\App\Domain\Meeting\Controllers\MeetingSeriesController::class, 'generateMeetings'])->name('meeting-series.generate');
```

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add meeting series CRUD controller, service, and routes"
```

---

## Task 12: Meeting Series - Views & Tests

**Files:**
- Create: `resources/views/meeting-series/index.blade.php`
- Create: `resources/views/meeting-series/create.blade.php`
- Create: `resources/views/meeting-series/edit.blade.php`
- Create: `resources/views/meeting-series/show.blade.php`
- Create: `tests/Feature/Domain/Meeting/Controllers/MeetingSeriesControllerTest.php`

**Step 1: Create views**

- Index: list series with name, recurrence pattern, active badge, meeting count
- Create/edit: form with name, description, recurrence_pattern select, is_active toggle
- Show: series details + list of child meetings + "Generate Meetings" button

**Step 2: Create tests**

Tests: list series, create series, update series, delete series, generate meetings from series.

**Step 3: Run tests**

Run: `php artisan test --compact --filter=MeetingSeriesController`

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add meeting series views and tests"
```

---

## Task 13: Export Domain - Services

**Files:**
- Create: `app/Domain/Export/Services/PdfExportService.php`
- Create: `app/Domain/Export/Services/WordExportService.php`
- Create: `app/Domain/Export/Services/CsvExportService.php`
- Create: `resources/views/exports/meeting-pdf.blade.php`

**Step 1: Create PDF export Blade template**

A clean, printable Blade view (no extends layout) with: meeting title, date, status, location, attendees list, content/summary, action items table, decisions. Use inline CSS for PDF rendering.

**Step 2: Create PdfExportService**

```php
<?php
// app/Domain/Export/Services/PdfExportService.php
declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfExportService
{
    public function export(MinutesOfMeeting $meeting): Response
    {
        $meeting->load(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes']);

        $pdf = Pdf::loadView('exports.meeting-pdf', compact('meeting'));

        return $pdf->download("meeting-{$meeting->id}.pdf");
    }
}
```

**Step 3: Create WordExportService**

Use PhpWord to create .docx with sections: Title, Meeting Info, Attendees, Content, Action Items.

**Step 4: Create CsvExportService**

Export action items as CSV: Title, Description, Assignee, Priority, Status, Due Date, Created At.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add export services for PDF, Word, and CSV"
```

---

## Task 14: Export Domain - Controller, Routes & Tests

**Files:**
- Create: `app/Domain/Export/Controllers/ExportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/meetings/show.blade.php` (add export buttons)
- Create: `tests/Feature/Domain/Export/Controllers/ExportControllerTest.php`

**Step 1: Create ExportController**

```php
<?php
// app/Domain/Export/Controllers/ExportController.php
declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Export\Services\CsvExportService;
use App\Domain\Export\Services\PdfExportService;
use App\Domain\Export\Services\WordExportService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

class ExportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PdfExportService $pdfExportService,
        private WordExportService $wordExportService,
        private CsvExportService $csvExportService,
    ) {}

    public function pdf(MinutesOfMeeting $meeting): mixed
    {
        $this->authorize('view', $meeting);
        return $this->pdfExportService->export($meeting);
    }

    public function word(MinutesOfMeeting $meeting): mixed
    {
        $this->authorize('view', $meeting);
        return $this->wordExportService->export($meeting);
    }

    public function csv(MinutesOfMeeting $meeting): mixed
    {
        $this->authorize('view', $meeting);
        return $this->csvExportService->export($meeting);
    }
}
```

**Step 2: Add export routes (inside meetings prefix group)**

```php
Route::get('export/pdf', [\App\Domain\Export\Controllers\ExportController::class, 'pdf'])->name('export.pdf');
Route::get('export/word', [\App\Domain\Export\Controllers\ExportController::class, 'word'])->name('export.word');
Route::get('export/csv', [\App\Domain\Export\Controllers\ExportController::class, 'csv'])->name('export.csv');
```

**Step 3: Add export dropdown to meeting show page**

Add an "Export" dropdown button next to the edit/finalize buttons with PDF, Word, CSV options.

**Step 4: Create tests**

Tests: can export PDF, can export Word, can export CSV, unauthenticated user cannot export.

**Step 5: Run tests**

Run: `php artisan test --compact --filter=ExportController`

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add export controller, routes, and tests"
```

---

## Task 15: Collaboration - Share Controller & Service

**Files:**
- Create: `app/Domain/Collaboration/Controllers/ShareController.php`
- Create: `app/Domain/Collaboration/Services/ShareService.php`
- Create: `app/Domain/Collaboration/Requests/CreateShareRequest.php`
- Create: `app/Domain/Collaboration/Policies/MeetingSharePolicy.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create MeetingSharePolicy**

View shares: `view_meeting` permission. Create/delete shares: `edit_meeting` permission.

**Step 2: Create CreateShareRequest**

Rules: shared_with_user_id (nullable, exists:users,id), permission (required, in:view,comment,edit), expires_at (nullable, date, after:now).

**Step 3: Create ShareService**

Methods:
- `shareWithUser(meeting, userId, permission, sharedBy)` - create user share
- `generateShareLink(meeting, permission, sharedBy, expiresAt)` - create token-based share
- `revokeShare(share)` - soft delete
- `getSharesForMeeting(meeting)` - list all shares

**Step 4: Create ShareController**

Methods: `index`, `store`, `destroy`.

**Step 5: Add routes**

```php
Route::resource('shares', \App\Domain\Collaboration\Controllers\ShareController::class)->only(['index', 'store', 'destroy']);
```

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add meeting share controller, service, and routes"
```

---

## Task 16: Collaboration - Comment Controller & Service

**Files:**
- Create: `app/Domain/Collaboration/Controllers/CommentController.php`
- Create: `app/Domain/Collaboration/Services/CommentService.php`
- Create: `app/Domain/Collaboration/Requests/CreateCommentRequest.php`
- Modify: `routes/web.php`

**Step 1: Create CreateCommentRequest**

Rules: body (required, string, max:2000), parent_id (nullable, exists:comments,id).

**Step 2: Create CommentService**

Methods:
- `addComment(commentable, user, body, parentId)` - create comment
- `updateComment(comment, body)` - update comment body
- `deleteComment(comment)` - soft delete
- `getComments(commentable)` - get threaded comments

**Step 3: Create CommentController**

Nested under meetings: `store`, `update`, `destroy`.

**Step 4: Add routes**

```php
Route::post('comments', [\App\Domain\Collaboration\Controllers\CommentController::class, 'store'])->name('comments.store');
Route::put('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'update'])->name('comments.update');
Route::delete('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'destroy'])->name('comments.destroy');
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add comment controller, service, and routes"
```

---

## Task 17: Collaboration - Views & Tests

**Files:**
- Create: `resources/views/collaboration/share-panel.blade.php` (partial for meeting show)
- Create: `resources/views/collaboration/comments.blade.php` (partial for meeting show)
- Modify: `resources/views/meetings/show.blade.php` (add Sharing + Comments tabs)
- Create: `tests/Feature/Domain/Collaboration/Controllers/ShareControllerTest.php`
- Create: `tests/Feature/Domain/Collaboration/Controllers/CommentControllerTest.php`

**Step 1: Create share panel partial**

List current shares, form to add new share (select user + permission), generated share link display.

**Step 2: Create comments partial**

Threaded comment list with reply button, add comment form at bottom. Use Alpine.js for reply toggle.

**Step 3: Add Sharing and Comments tabs to meeting show**

Add two new tabs in the meeting show tabs: "Sharing" and "Comments".

**Step 4: Create tests**

ShareController tests: can share meeting, can revoke share, can generate share link.
CommentController tests: can add comment, can reply to comment, can delete own comment.

**Step 5: Run tests**

Run: `php artisan test --compact --filter="ShareController|CommentController"`

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add collaboration views and tests"
```

---

## Task 18: Analytics Domain - Service & Controller

**Files:**
- Create: `app/Domain/Analytics/Services/AnalyticsService.php`
- Create: `app/Domain/Analytics/Controllers/AnalyticsController.php`
- Modify: `routes/web.php`

**Step 1: Create AnalyticsService**

Methods:
- `getMeetingStats(orgId, startDate, endDate)` - meetings per month, status distribution, avg duration
- `getActionItemStats(orgId, startDate, endDate)` - completion rate, overdue count, per-user breakdown
- `getParticipationStats(orgId, startDate, endDate)` - top attendees, attendance rate
- `getAiUsageStats(orgId, startDate, endDate)` - extraction count, chat sessions, transcription count

All methods return arrays suitable for JSON/Chart.js consumption.

**Step 2: Create AnalyticsController**

```php
<?php
// app/Domain/Analytics/Controllers/AnalyticsController.php
declare(strict_types=1);

namespace App\Domain\Analytics\Controllers;

use App\Domain\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    public function index(): View
    {
        $orgId = auth()->user()->current_organization_id;
        $startDate = now()->subMonths(6)->startOfMonth();
        $endDate = now()->endOfMonth();

        $meetingStats = $this->analyticsService->getMeetingStats($orgId, $startDate, $endDate);
        $actionStats = $this->analyticsService->getActionItemStats($orgId, $startDate, $endDate);
        $participationStats = $this->analyticsService->getParticipationStats($orgId, $startDate, $endDate);
        $aiStats = $this->analyticsService->getAiUsageStats($orgId, $startDate, $endDate);

        return view('analytics.index', compact('meetingStats', 'actionStats', 'participationStats', 'aiStats'));
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = auth()->user()->current_organization_id;
        $startDate = $request->date('start_date', now()->subMonths(6)->startOfMonth());
        $endDate = $request->date('end_date', now()->endOfMonth());

        return response()->json([
            'meetings' => $this->analyticsService->getMeetingStats($orgId, $startDate, $endDate),
            'actions' => $this->analyticsService->getActionItemStats($orgId, $startDate, $endDate),
            'participation' => $this->analyticsService->getParticipationStats($orgId, $startDate, $endDate),
            'ai' => $this->analyticsService->getAiUsageStats($orgId, $startDate, $endDate),
        ]);
    }
}
```

**Step 3: Add routes**

```php
Route::get('analytics', [\App\Domain\Analytics\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
Route::get('analytics/data', [\App\Domain\Analytics\Controllers\AnalyticsController::class, 'data'])->name('analytics.data');
```

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add analytics service and controller"
```

---

## Task 19: Analytics Domain - View & Tests

**Files:**
- Create: `resources/views/analytics/index.blade.php`
- Create: `tests/Feature/Domain/Analytics/Controllers/AnalyticsControllerTest.php`

**Step 1: Create analytics view**

Full page with:
- Date range picker (Alpine.js with two date inputs)
- Summary stat cards row (total meetings, completion rate, overdue items, AI extractions)
- Row 1: Meetings per month bar chart + Status distribution donut chart
- Row 2: Action item completion trend line chart + Per-user action items bar chart
- Row 3: Top attendees list + AI usage stats

Use Chart.js via CDN: `<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>`

Alpine.js component to fetch data from `/analytics/data` endpoint and render charts.

**Step 2: Create tests**

Tests: can view analytics page, can fetch analytics data as JSON, date range filter works.

**Step 3: Run tests**

Run: `php artisan test --compact --filter=AnalyticsController`

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add analytics view with Chart.js charts and tests"
```

---

## Task 20: Enhanced Dashboard

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`

**Step 1: Enhance DashboardController**

Add more stats:
- `meetings_this_week` - count of meetings created this week
- `completion_rate` - percentage of completed action items
- `upcoming_meetings` - meetings in next 7 days
- `recent_activity` - last 10 audit log entries for org (use AuditLog model if available)

**Step 2: Enhance dashboard view**

- Add new stat cards: Meetings This Week, Completion Rate (%)
- Add "Upcoming Meetings" section (next 7 days)
- Add "Activity Feed" section (recent org-wide changes)
- Add quick link button to Analytics page
- Keep existing stats + recent meetings + action items sections

**Step 3: Run existing dashboard test**

Run: `php artisan test --compact --filter=DashboardTest`

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: enhance dashboard with more stats, activity feed, and quick links"
```

---

## Task 21: Navigation Updates

**Files:**
- Modify: `resources/views/layouts/partials/icon-rail.blade.php`
- Modify: `resources/views/layouts/partials/flyout-panel.blade.php`
- Modify: `resources/views/layouts/partials/mobile-bottom-nav.blade.php`

**Step 1: Update icon rail**

- Analytics: set `'active' => request()->routeIs('analytics.*')`
- Add profile link to avatar button

**Step 2: Update flyout panel**

- Analytics: replace "Coming in Phase 2" with actual link to analytics page
- Settings: add links to Meeting Templates, Meeting Series
- Profile: add "Edit Profile" link

**Step 3: Update mobile bottom nav**

Add analytics icon to mobile nav if not already there.

**Step 4: Commit**

```bash
git add resources/views/layouts/
git commit -m "feat: update navigation with links to analytics, templates, series, profile"
```

---

## Task 22: Run Pint & Full Test Suite

**Step 1: Run Pint on all modified files**

Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests PASS

**Step 3: Fix any failures, re-run**

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: fix formatting with Pint and ensure all tests pass"
```

---

## Summary

| Task | Feature | Key Files |
|------|---------|-----------|
| 1 | Dependencies | composer.json |
| 2 | Enums & Migrations | SharePermission, meeting_shares, comments |
| 3 | Collaboration Models | MeetingShare, Comment + factories |
| 4 | User Profile Backend | ProfileController, requests, routes |
| 5 | User Profile View | profile/edit.blade.php |
| 6 | User Profile Tests | ProfileControllerTest |
| 7 | Org Settings | Enhanced controller, request, view |
| 8 | Templates Backend | Controller, service, requests, policy, routes |
| 9 | Templates Views | index, create, edit, show |
| 10 | Templates Tests | MeetingTemplateControllerTest |
| 11 | Series Backend | Controller, service, requests, policy, routes |
| 12 | Series Views+Tests | Views + MeetingSeriesControllerTest |
| 13 | Export Services | PdfExportService, WordExportService, CsvExportService |
| 14 | Export Controller+Tests | ExportController, routes, tests |
| 15 | Share Backend | ShareController, ShareService, policy |
| 16 | Comment Backend | CommentController, CommentService |
| 17 | Collaboration Views+Tests | Partials, tabs, tests |
| 18 | Analytics Backend | AnalyticsService, AnalyticsController |
| 19 | Analytics View+Tests | Chart.js view, tests |
| 20 | Enhanced Dashboard | DashboardController, dashboard.blade.php |
| 21 | Navigation Updates | icon-rail, flyout-panel, mobile nav |
| 22 | Pint & Final Tests | Formatting + full test suite |
