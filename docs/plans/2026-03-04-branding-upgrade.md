# Branding Upgrade Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Upgrade the Super Admin branding page with file uploads (logo, favicon, login background), extended color palette, font selection, live preview panel (sidebar + login tabs), theme presets (built-in + custom saved), and reset-to-default.

**Architecture:** Alpine.js + standard multipart form. BrandingController extended to handle `UploadedFile`, store to `storage/app/public/branding/`. New preset routes for AJAX save/delete. Two-column layout: form left (60%), sticky preview right (40%).

**Tech Stack:** Laravel 12, Blade, Alpine.js v3, Tailwind v4, PlatformSetting model (key/value JSON store)

---

### Task 1: BrandingService — add new default keys

**Files:**
- Modify: `app/Domain/Admin/Services/BrandingService.php`

**Step 1: Write failing test**

In `tests/Feature/Admin/BrandingTest.php`, add after the existing tests:

```php
test('branding service includes new default keys', function () {
    $service = app(BrandingService::class);
    $all = $service->all();

    expect($all)
        ->toHaveKey('accent_color', '#10b981')
        ->toHaveKey('danger_color', '#ef4444')
        ->toHaveKey('success_color', '#22c55e')
        ->toHaveKey('heading_font', 'Inter')
        ->toHaveKey('body_font', 'Inter')
        ->toHaveKey('custom_themes', '[]')
        ->toHaveKey('logo_path', '')
        ->toHaveKey('favicon_path', '')
        ->toHaveKey('login_background_path', '');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --filter="branding service includes new default keys"
```

Expected: FAIL — keys not in DEFAULTS array.

**Step 3: Update BrandingService::DEFAULTS**

In `app/Domain/Admin/Services/BrandingService.php`, update the `DEFAULTS` const:

```php
private const array DEFAULTS = [
    'app_name' => 'antaraFLOW',
    'primary_color' => '#7c3aed',
    'secondary_color' => '#3b82f6',
    'accent_color' => '#10b981',
    'danger_color' => '#ef4444',
    'success_color' => '#22c55e',
    'footer_text' => '',
    'support_email' => '',
    'custom_css' => '',
    'custom_domain' => '',
    'logo_url' => '',
    'favicon_url' => '',
    'login_background_url' => '',
    'logo_path' => '',
    'favicon_path' => '',
    'login_background_path' => '',
    'email_header_html' => '',
    'email_footer_html' => '',
    'heading_font' => 'Inter',
    'body_font' => 'Inter',
    'custom_themes' => '[]',
];
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --filter="branding service includes new default keys"
```

Expected: PASS.

**Step 5: Run full test suite to check no regressions**

```bash
php artisan test --compact
```

Expected: All passing.

**Step 6: Commit**

```bash
git add app/Domain/Admin/Services/BrandingService.php tests/Feature/Admin/BrandingTest.php
git commit -m "feat(branding): add new default keys for extended branding settings"
```

---

### Task 2: Add preset routes

**Files:**
- Modify: `routes/admin.php`

**Step 1: Add preset routes in the Branding section**

In `routes/admin.php`, replace the branding section:

```php
// Branding
Route::get('branding', [BrandingController::class, 'index'])->name('branding.index');
Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');
Route::post('branding/presets', [BrandingController::class, 'storePreset'])->name('branding.presets.store');
Route::delete('branding/presets/{name}', [BrandingController::class, 'destroyPreset'])->name('branding.presets.destroy');
```

**Step 2: Run tests**

```bash
php artisan test --compact
```

Expected: All passing (routes exist but methods don't yet — test coverage for presets added later).

**Step 3: Commit**

```bash
git add routes/admin.php
git commit -m "feat(branding): add preset store/destroy routes"
```

---

### Task 3: BrandingController — file uploads + presets

**Files:**
- Modify: `app/Domain/Admin/Controllers/BrandingController.php`

**Step 1: Write failing tests for file upload**

In `tests/Feature/Admin/BrandingTest.php`, add:

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can upload logo file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $file,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertExists('branding/' . $file->hashName());
    expect(PlatformSetting::getValue('logo_path'))->toContain('branding/');
});

test('admin can upload favicon file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('favicon.ico', 32, 32);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'favicon' => $file,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertExists('branding/' . $file->hashName());
    expect(PlatformSetting::getValue('favicon_path'))->toContain('branding/');
});

test('logo upload rejects non-image files', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('malware.php', 100, 'application/php');

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $file,
        ]))
        ->assertSessionHasErrors('logo');
});

test('admin can save a custom theme preset', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.branding.presets.store'), [
            'name' => 'My Theme',
            'primary_color' => '#ff0000',
            'secondary_color' => '#00ff00',
            'accent_color' => '#0000ff',
            'danger_color' => '#ff4444',
            'success_color' => '#44ff44',
            'heading_font' => 'Poppins',
            'body_font' => 'Inter',
        ])
        ->assertJson(['success' => true]);

    $themes = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
    expect($themes)->toHaveCount(1);
    expect($themes[0]['name'])->toBe('My Theme');
});

test('admin can delete a custom theme preset', function () {
    PlatformSetting::setValue('custom_themes', json_encode([
        ['name' => 'My Theme', 'primary_color' => '#ff0000'],
    ]));

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.branding.presets.destroy', 'My Theme'))
        ->assertJson(['success' => true]);

    $themes = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
    expect($themes)->toHaveCount(0);
});

// Helper at top of file (outside tests):
function basePayload(): array
{
    return [
        'app_name' => 'TestBrand',
        'primary_color' => '#7c3aed',
        'secondary_color' => '#3b82f6',
        'accent_color' => '#10b981',
        'danger_color' => '#ef4444',
        'success_color' => '#22c55e',
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
        'footer_text' => '',
        'support_email' => '',
        'custom_css' => '',
        'custom_domain' => '',
        'logo_url' => '',
        'favicon_url' => '',
        'login_background_url' => '',
        'email_header_html' => '',
        'email_footer_html' => '',
    ];
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter="admin can upload logo|admin can upload favicon|logo upload rejects|admin can save a custom theme|admin can delete a custom theme"
```

Expected: FAIL — methods don't exist yet.

**Step 3: Update BrandingController**

Replace entire `app/Domain/Admin/Controllers/BrandingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function __construct(
        private BrandingService $brandingService,
    ) {}

    public function index(): View
    {
        $settings = $this->brandingService->all();

        return view('admin.branding.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'string', 'max:7'],
            'secondary_color' => ['required', 'string', 'max:7'],
            'accent_color' => ['nullable', 'string', 'max:7'],
            'danger_color' => ['nullable', 'string', 'max:7'],
            'success_color' => ['nullable', 'string', 'max:7'],
            'heading_font' => ['nullable', 'string', 'max:100'],
            'body_font' => ['nullable', 'string', 'max:100'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'custom_css' => ['nullable', 'string'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'login_background_url' => ['nullable', 'string', 'max:500'],
            'email_header_html' => ['nullable', 'string'],
            'email_footer_html' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:2048'],
            'login_background' => ['nullable', 'image', 'max:5120'],
        ]);

        // Handle file uploads
        foreach (['logo', 'favicon', 'login_background'] as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('branding', 'public');
                PlatformSetting::setValue("{$field}_path", $path);
            }
        }

        // Save text fields
        $textFields = collect($validated)->except(['logo', 'favicon', 'login_background']);
        foreach ($textFields as $key => $value) {
            PlatformSetting::setValue($key, $value ?? '');
        }

        $this->brandingService->clearCache();

        return redirect()->route('admin.branding.index')
            ->with('success', 'Branding settings updated successfully.');
    }

    public function storePreset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'primary_color' => ['required', 'string', 'max:7'],
            'secondary_color' => ['required', 'string', 'max:7'],
            'accent_color' => ['nullable', 'string', 'max:7'],
            'danger_color' => ['nullable', 'string', 'max:7'],
            'success_color' => ['nullable', 'string', 'max:7'],
            'heading_font' => ['nullable', 'string', 'max:100'],
            'body_font' => ['nullable', 'string', 'max:100'],
        ]);

        $existing = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true) ?? [];

        // Replace if same name exists
        $existing = array_filter($existing, fn ($t) => $t['name'] !== $validated['name']);
        $existing[] = $validated;

        PlatformSetting::setValue('custom_themes', json_encode(array_values($existing)));
        $this->brandingService->clearCache();

        return response()->json(['success' => true]);
    }

    public function destroyPreset(string $name): JsonResponse
    {
        $existing = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true) ?? [];
        $existing = array_filter($existing, fn ($t) => $t['name'] !== $name);

        PlatformSetting::setValue('custom_themes', json_encode(array_values($existing)));
        $this->brandingService->clearCache();

        return response()->json(['success' => true]);
    }
}
```

**Step 4: Run tests**

```bash
php artisan test --filter="admin can upload logo|admin can upload favicon|logo upload rejects|admin can save a custom theme|admin can delete a custom theme"
```

Expected: PASS.

**Step 5: Run full suite**

```bash
php artisan test --compact
```

Expected: All passing.

**Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 7: Commit**

```bash
git add app/Domain/Admin/Controllers/BrandingController.php tests/Feature/Admin/BrandingTest.php
git commit -m "feat(branding): add file upload and preset store/destroy to BrandingController"
```

---

### Task 4: Ensure storage symlink exists

**Step 1: Run artisan storage:link**

```bash
php artisan storage:link --no-interaction
```

Expected: `The [public/storage] link has been connected to [storage/app/public]` (or already exists).

---

### Task 5: Update existing branding tests for new fields

**Files:**
- Modify: `tests/Feature/Admin/BrandingTest.php`

**Step 1: Update `admin can update branding settings` and `updated branding persists in database` tests**

The existing `put` calls are missing the new required fields. Update `admin can update branding settings` payload to include new fields:

```php
test('admin can update branding settings', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), basePayload())
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('success', 'Branding settings updated successfully.');
});
```

Update `updated branding persists in database` to use `basePayload()` merged with specific values:

```php
test('updated branding persists in database', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'app_name' => 'PersistBrand',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'footer_text' => 'My footer',
            'support_email' => 'support@test.com',
            'custom_css' => 'body { color: red; }',
            'custom_domain' => 'app.test.com',
            'logo_url' => 'https://example.com/logo.png',
        ]));

    expect(PlatformSetting::getValue('app_name'))->toBe('PersistBrand');
    expect(PlatformSetting::getValue('primary_color'))->toBe('#123456');
    expect(PlatformSetting::getValue('support_email'))->toBe('support@test.com');
    expect(PlatformSetting::getValue('custom_css'))->toBe('body { color: red; }');
    expect(PlatformSetting::getValue('custom_domain'))->toBe('app.test.com');
    expect(PlatformSetting::getValue('logo_url'))->toBe('https://example.com/logo.png');
});
```

**Step 2: Run tests**

```bash
php artisan test --filter=BrandingTest
```

Expected: All branding tests pass.

**Step 3: Commit**

```bash
git add tests/Feature/Admin/BrandingTest.php
git commit -m "test(branding): update existing tests to use new payload fields"
```

---

### Task 6: Rewrite branding view — two-column layout + Alpine wrapper

**Files:**
- Modify: `resources/views/admin/branding/index.blade.php`

**Step 1: Replace entire view**

This is the full view. Replace `resources/views/admin/branding/index.blade.php` with:

```blade
@extends('admin.layouts.app')

@section('title', 'Platform Branding')
@section('page-title', 'Platform Branding')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Branding</span>
    </nav>
@endsection

@section('content')
<div
    x-data="brandingForm()"
    x-init="init()"
    class="flex gap-8 items-start"
>
    {{-- Left: Form --}}
    <div class="flex-1 min-w-0 space-y-6">

        {{-- Theme Presets --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Theme Presets</h3>
                <div class="flex gap-2">
                    <button type="button" @click="resetToDefaults()"
                            class="px-3 py-1.5 text-xs font-medium text-slate-300 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors">
                        Reset to Default
                    </button>
                    <button type="button" @click="saveCurrentAsPreset()"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors">
                        Save Current as Theme
                    </button>
                </div>
            </div>

            {{-- Built-in presets --}}
            <p class="text-xs text-slate-400 mb-3">Built-in</p>
            <div class="flex flex-wrap gap-3 mb-4">
                <template x-for="preset in builtInPresets" :key="preset.name">
                    <button type="button" @click="applyPreset(preset)"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 hover:border-slate-400 transition-colors bg-slate-700/50 text-sm text-slate-200">
                        <span class="w-4 h-4 rounded-full border border-white/20" :style="`background:${preset.primary_color}`"></span>
                        <span x-text="preset.name"></span>
                    </button>
                </template>
            </div>

            {{-- Custom presets --}}
            <template x-if="customPresets.length > 0">
                <div>
                    <p class="text-xs text-slate-400 mb-3">Custom</p>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="preset in customPresets" :key="preset.name">
                            <div class="flex items-center gap-1 px-3 py-2 rounded-lg border border-slate-600 bg-slate-700/50">
                                <button type="button" @click="applyPreset(preset)"
                                        class="flex items-center gap-2 text-sm text-slate-200">
                                    <span class="w-4 h-4 rounded-full border border-white/20" :style="`background:${preset.primary_color}`"></span>
                                    <span x-text="preset.name"></span>
                                </button>
                                <button type="button" @click="deletePreset(preset.name)"
                                        class="ml-1 text-slate-500 hover:text-red-400 transition-colors text-xs">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- The form --}}
        <form method="POST" action="{{ route('admin.branding.update') }}"
              enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Basic --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Basic</h3>
                <div class="space-y-5">
                    <div>
                        <label for="app_name" class="block text-sm font-medium text-slate-300 mb-1">App Name</label>
                        <input type="text" name="app_name" id="app_name"
                               :value="form.app_name"
                               @input="form.app_name = $event.target.value"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                        @error('app_name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Logo Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Logo</label>
                        <div class="flex items-start gap-4">
                            <div x-show="form.logo_preview || '{{ $settings['logo_path'] ? Storage::url($settings['logo_path']) : $settings['logo_url'] }}'"
                                 class="flex-shrink-0">
                                <img :src="form.logo_preview || '{{ $settings['logo_path'] ? Storage::url($settings['logo_path']) : $settings['logo_url'] }}'"
                                     class="h-16 w-auto rounded-lg border border-slate-600 object-contain bg-slate-700 p-1"
                                     x-show="form.logo_preview || '{{ $settings['logo_path'] ? Storage::url($settings['logo_path']) : $settings['logo_url'] }}'">
                            </div>
                            <div class="flex-1">
                                <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/50">
                                    <span class="text-sm text-slate-400">Click to upload or drag & drop</span>
                                    <span class="text-xs text-slate-500 mt-1">PNG, JPG, GIF — max 2MB</span>
                                    <input type="file" name="logo" accept="image/*" class="hidden"
                                           @change="handleFilePreview($event, 'logo_preview')">
                                </label>
                                <div class="mt-2">
                                    <input type="text" name="logo_url" placeholder="Or paste URL: https://example.com/logo.png"
                                           value="{{ old('logo_url', $settings['logo_url']) }}"
                                           @input="form.logo_preview = null; form.logo_url = $event.target.value"
                                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs">
                                </div>
                            </div>
                        </div>
                        @error('logo') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Favicon Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Favicon</label>
                        <div class="flex items-start gap-4">
                            <div x-show="form.favicon_preview || '{{ $settings['favicon_path'] ? Storage::url($settings['favicon_path']) : $settings['favicon_url'] }}'">
                                <img :src="form.favicon_preview || '{{ $settings['favicon_path'] ? Storage::url($settings['favicon_path']) : $settings['favicon_url'] }}'"
                                     class="h-8 w-8 rounded border border-slate-600 object-contain bg-slate-700 p-0.5"
                                     x-show="form.favicon_preview || '{{ $settings['favicon_path'] ? Storage::url($settings['favicon_path']) : $settings['favicon_url'] }}'">
                            </div>
                            <div class="flex-1">
                                <label class="flex flex-col items-center justify-center w-full h-16 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/50">
                                    <span class="text-sm text-slate-400">Click to upload favicon</span>
                                    <span class="text-xs text-slate-500">ICO, PNG — max 2MB, 32×32 ideal</span>
                                    <input type="file" name="favicon" accept="image/*" class="hidden"
                                           @change="handleFilePreview($event, 'favicon_preview')">
                                </label>
                                <div class="mt-2">
                                    <input type="text" name="favicon_url" placeholder="Or paste URL: https://example.com/favicon.ico"
                                           value="{{ old('favicon_url', $settings['favicon_url']) }}"
                                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs">
                                </div>
                            </div>
                        </div>
                        @error('favicon') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Colors --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Colors</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach([
                        ['key' => 'primary_color',   'label' => 'Primary'],
                        ['key' => 'secondary_color',  'label' => 'Secondary'],
                        ['key' => 'accent_color',     'label' => 'Accent'],
                        ['key' => 'danger_color',     'label' => 'Danger'],
                        ['key' => 'success_color',    'label' => 'Success'],
                    ] as $color)
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">{{ $color['label'] }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="{{ $color['key'] }}"
                                   :value="form.{{ $color['key'] }}"
                                   @input="form.{{ $color['key'] }} = $event.target.value"
                                   class="h-10 w-12 rounded border border-slate-600 bg-slate-700 cursor-pointer p-0.5">
                            <input type="text"
                                   :value="form.{{ $color['key'] }}"
                                   @input="form.{{ $color['key'] }} = $event.target.value"
                                   class="flex-1 bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono"
                                   maxlength="7">
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Typography --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Typography</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @foreach([
                        ['key' => 'heading_font', 'label' => 'Heading Font'],
                        ['key' => 'body_font',    'label' => 'Body Font'],
                    ] as $font)
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">{{ $font['label'] }}</label>
                        <select name="{{ $font['key'] }}"
                                :value="form.{{ $font['key'] }}"
                                @change="form.{{ $font['key'] }} = $event.target.value"
                                class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                            @foreach(['Inter','Poppins','Roboto','Lato','Montserrat','Open Sans','Nunito','Raleway','Source Sans Pro','DM Sans'] as $font_name)
                                <option value="{{ $font_name }}"
                                    {{ old($font['key'], $settings[$font['key']]) === $font_name ? 'selected' : '' }}>
                                    {{ $font_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Login Page --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Login Page</h3>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Background Image</label>
                    <div class="flex items-start gap-4">
                        <div x-show="form.bg_preview || '{{ $settings['login_background_path'] ? Storage::url($settings['login_background_path']) : $settings['login_background_url'] }}'">
                            <img :src="form.bg_preview || '{{ $settings['login_background_path'] ? Storage::url($settings['login_background_path']) : $settings['login_background_url'] }}'"
                                 class="h-20 w-32 rounded-lg border border-slate-600 object-cover"
                                 x-show="form.bg_preview || '{{ $settings['login_background_path'] ? Storage::url($settings['login_background_path']) : $settings['login_background_url'] }}'">
                        </div>
                        <div class="flex-1">
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/50">
                                <span class="text-sm text-slate-400">Click to upload background</span>
                                <span class="text-xs text-slate-500">JPG, PNG — max 5MB</span>
                                <input type="file" name="login_background" accept="image/*" class="hidden"
                                       @change="handleFilePreview($event, 'bg_preview')">
                            </label>
                            <div class="mt-2">
                                <input type="text" name="login_background_url" placeholder="Or paste URL"
                                       value="{{ old('login_background_url', $settings['login_background_url']) }}"
                                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Contact & Footer</h3>
                <div class="space-y-5">
                    <div>
                        <label for="footer_text" class="block text-sm font-medium text-slate-300 mb-1">Footer Text</label>
                        <textarea name="footer_text" id="footer_text" rows="3"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">{{ old('footer_text', $settings['footer_text']) }}</textarea>
                    </div>
                    <div>
                        <label for="support_email" class="block text-sm font-medium text-slate-300 mb-1">Support Email</label>
                        <input type="email" name="support_email" id="support_email"
                               value="{{ old('support_email', $settings['support_email']) }}"
                               placeholder="support@example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            {{-- Advanced --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Advanced</h3>
                <div class="space-y-5">
                    <div>
                        <label for="custom_domain" class="block text-sm font-medium text-slate-300 mb-1">Custom Domain</label>
                        <input type="text" name="custom_domain" id="custom_domain"
                               value="{{ old('custom_domain', $settings['custom_domain']) }}"
                               placeholder="app.yourdomain.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="custom_css" class="block text-sm font-medium text-slate-300 mb-1">Custom CSS</label>
                        <textarea name="custom_css" id="custom_css" rows="8"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('custom_css', $settings['custom_css']) }}</textarea>
                    </div>
                    <div>
                        <label for="email_header_html" class="block text-sm font-medium text-slate-300 mb-1">Email Header HTML</label>
                        <textarea name="email_header_html" id="email_header_html" rows="4"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('email_header_html', $settings['email_header_html']) }}</textarea>
                    </div>
                    <div>
                        <label for="email_footer_html" class="block text-sm font-medium text-slate-300 mb-1">Email Footer HTML</label>
                        <textarea name="email_footer_html" id="email_footer_html" rows="4"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('email_footer_html', $settings['email_footer_html']) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-4 pb-8">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Branding
                </button>
            </div>
        </form>
    </div>

    {{-- Right: Live Preview (sticky) --}}
    <div class="w-80 flex-shrink-0 sticky top-8 space-y-4">
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
            <div class="flex border-b border-slate-700">
                <button type="button" @click="previewTab = 'sidebar'"
                        :class="previewTab === 'sidebar' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 px-4 py-3 text-xs font-medium transition-colors">
                    App Sidebar
                </button>
                <button type="button" @click="previewTab = 'login'"
                        :class="previewTab === 'login' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 px-4 py-3 text-xs font-medium transition-colors">
                    Login Page
                </button>
            </div>

            {{-- Sidebar preview --}}
            <div x-show="previewTab === 'sidebar'" class="p-3">
                <div class="rounded-lg overflow-hidden border border-slate-600" style="height: 300px;">
                    <div class="flex h-full">
                        {{-- Mock sidebar --}}
                        <div class="w-32 flex flex-col" :style="`background-color: ${form.primary_color}22; border-right: 1px solid ${form.primary_color}44`">
                            <div class="p-3 border-b" :style="`border-color: ${form.primary_color}44`">
                                <template x-if="form.logo_preview">
                                    <img :src="form.logo_preview" class="h-6 w-auto object-contain">
                                </template>
                                <template x-if="!form.logo_preview">
                                    <span class="text-xs font-bold text-white truncate" x-text="form.app_name || 'antaraFLOW'"></span>
                                </template>
                            </div>
                            <div class="flex-1 p-2 space-y-1">
                                <template x-for="item in ['Dashboard','Users','Settings']">
                                    <div class="px-2 py-1.5 rounded text-xs text-white/70 hover:text-white transition-colors"
                                         :style="`hover:background-color: ${form.primary_color}33`"
                                         x-text="item"></div>
                                </template>
                                <div class="px-2 py-1.5 rounded text-xs text-white font-medium"
                                     :style="`background-color: ${form.primary_color}55`">
                                    Active Page
                                </div>
                            </div>
                        </div>
                        {{-- Mock content --}}
                        <div class="flex-1 bg-slate-900 p-3">
                            <div class="h-3 w-24 rounded bg-slate-700 mb-3"></div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="h-12 rounded-lg" :style="`background-color: ${form.primary_color}22; border: 1px solid ${form.primary_color}44`"></div>
                                <div class="h-12 rounded-lg" :style="`background-color: ${form.accent_color}22; border: 1px solid ${form.accent_color}44`"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="h-2 w-full rounded bg-slate-700"></div>
                                <div class="h-2 w-3/4 rounded bg-slate-700"></div>
                                <div class="h-2 w-1/2 rounded bg-slate-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Login preview --}}
            <div x-show="previewTab === 'login'" class="p-3">
                <div class="rounded-lg overflow-hidden border border-slate-600 relative" style="height: 300px;">
                    {{-- Background --}}
                    <div class="absolute inset-0"
                         :style="(form.bg_preview) ? `background-image: url(${form.bg_preview}); background-size: cover; background-position: center; filter: blur(2px) brightness(0.4)` : 'background-color: #0f172a'">
                    </div>
                    {{-- Login card --}}
                    <div class="absolute inset-0 flex items-center justify-center p-4">
                        <div class="w-full max-w-xs bg-slate-800/90 rounded-xl p-4 border border-slate-700">
                            <div class="text-center mb-3">
                                <template x-if="form.logo_preview">
                                    <img :src="form.logo_preview" class="h-8 w-auto object-contain mx-auto mb-2">
                                </template>
                                <p class="text-xs font-bold text-white" x-text="form.app_name || 'antaraFLOW'"></p>
                            </div>
                            <div class="space-y-2 mb-3">
                                <div class="h-7 rounded bg-slate-700 border border-slate-600"></div>
                                <div class="h-7 rounded bg-slate-700 border border-slate-600"></div>
                            </div>
                            <div class="h-7 rounded text-center text-xs text-white flex items-center justify-center font-medium"
                                 :style="`background-color: ${form.primary_color}`">
                                Sign In
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Color chips --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
            <p class="text-xs text-slate-400 mb-3">Color Palette</p>
            <div class="flex gap-2">
                <template x-for="[label, key] in [['P','primary_color'],['S','secondary_color'],['A','accent_color'],['D','danger_color'],['OK','success_color']]">
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-8 h-8 rounded-full border-2 border-white/10" :style="`background-color: ${form[key]}`"></div>
                        <span class="text-xs text-slate-500" x-text="label"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function brandingForm() {
    return {
        previewTab: 'sidebar',

        form: {
            app_name: @json($settings['app_name']),
            primary_color: @json($settings['primary_color']),
            secondary_color: @json($settings['secondary_color']),
            accent_color: @json($settings['accent_color']),
            danger_color: @json($settings['danger_color']),
            success_color: @json($settings['success_color']),
            heading_font: @json($settings['heading_font']),
            body_font: @json($settings['body_font']),
            logo_preview: null,
            favicon_preview: null,
            bg_preview: null,
        },

        builtInPresets: [
            { name: 'Default Purple', primary_color: '#7c3aed', secondary_color: '#3b82f6', accent_color: '#10b981', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Inter', body_font: 'Inter' },
            { name: 'Ocean Blue',     primary_color: '#0ea5e9', secondary_color: '#06b6d4', accent_color: '#f59e0b', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Poppins', body_font: 'Inter' },
            { name: 'Forest Green',  primary_color: '#16a34a', secondary_color: '#15803d', accent_color: '#3b82f6', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Nunito', body_font: 'Nunito' },
            { name: 'Sunset Orange', primary_color: '#ea580c', secondary_color: '#dc2626', accent_color: '#7c3aed', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Montserrat', body_font: 'Inter' },
            { name: 'Minimal Dark',  primary_color: '#374151', secondary_color: '#6b7280', accent_color: '#f3f4f6', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Inter', body_font: 'Inter' },
        ],

        customPresets: @json(json_decode($settings['custom_themes'] ?? '[]', true) ?: []),

        defaults: {
            app_name: 'antaraFLOW',
            primary_color: '#7c3aed',
            secondary_color: '#3b82f6',
            accent_color: '#10b981',
            danger_color: '#ef4444',
            success_color: '#22c55e',
            heading_font: 'Inter',
            body_font: 'Inter',
        },

        init() {},

        handleFilePreview(event, previewKey) {
            const file = event.target.files[0];
            if (!file) { return; }
            const reader = new FileReader();
            reader.onload = (e) => { this.form[previewKey] = e.target.result; };
            reader.readAsDataURL(file);
        },

        applyPreset(preset) {
            this.form.primary_color = preset.primary_color;
            this.form.secondary_color = preset.secondary_color;
            this.form.accent_color = preset.accent_color || this.form.accent_color;
            this.form.danger_color = preset.danger_color || this.form.danger_color;
            this.form.success_color = preset.success_color || this.form.success_color;
            this.form.heading_font = preset.heading_font || this.form.heading_font;
            this.form.body_font = preset.body_font || this.form.body_font;

            // Sync hidden inputs
            ['primary_color','secondary_color','accent_color','danger_color','success_color'].forEach(key => {
                const el = document.querySelector(`input[name="${key}"]`);
                if (el) { el.value = this.form[key]; }
            });
        },

        resetToDefaults() {
            Object.assign(this.form, this.defaults);
        },

        async saveCurrentAsPreset() {
            const name = prompt('Enter a name for this theme:');
            if (!name || !name.trim()) { return; }

            const payload = {
                name: name.trim(),
                primary_color: this.form.primary_color,
                secondary_color: this.form.secondary_color,
                accent_color: this.form.accent_color,
                danger_color: this.form.danger_color,
                success_color: this.form.success_color,
                heading_font: this.form.heading_font,
                body_font: this.form.body_font,
            };

            const response = await fetch('{{ route('admin.branding.presets.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                this.customPresets = this.customPresets.filter(p => p.name !== name.trim());
                this.customPresets.push(payload);
            }
        },

        async deletePreset(name) {
            if (!confirm(`Delete theme "${name}"?`)) { return; }

            const response = await fetch(`{{ url('admin/branding/presets') }}/${encodeURIComponent(name)}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });

            if (response.ok) {
                this.customPresets = this.customPresets.filter(p => p.name !== name);
            }
        },
    };
}
</script>
@endsection
```

**Step 2: Verify page loads**

Navigate to `https://antaraflow.test/admin/branding` — verify no PHP errors, two-column layout shows.

**Step 3: Run full test suite**

```bash
php artisan test --compact
```

Expected: All passing.

**Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 5: Commit**

```bash
git add resources/views/admin/branding/index.blade.php
git commit -m "feat(branding): rework branding page with file upload, live preview, theme presets, extended colors"
```

---

### Task 7: Add Storage facade import to view (if needed)

**Step 1: Verify Storage::url works in the view**

If the view errors on `Storage::url()`, add at top of the blade file (after `@extends`):

```blade
@php use Illuminate\Support\Facades\Storage; @endphp
```

**Step 2: Check in browser — no errors**

**Step 3: Commit if changed**

```bash
git add resources/views/admin/branding/index.blade.php
git commit -m "fix(branding): add Storage facade import to branding view"
```

---

### Task 8: Final test run + verification

**Step 1: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 2: Manual browser check**

Visit `https://antaraflow.test/admin/branding` and verify:
- [ ] Two-column layout renders correctly
- [ ] Color pickers update live preview in real-time
- [ ] App name field updates sidebar preview text
- [ ] Tab switching between Sidebar and Login works
- [ ] Built-in preset cards appear and apply colors when clicked
- [ ] File upload area appears for logo, favicon, login background
- [ ] "Save Current as Theme" prompt appears and saves
- [ ] "Reset to Default" resets colors

**Step 3: Commit any fixes**

**Step 4: Final Pint pass**

```bash
vendor/bin/pint --dirty --format agent
```
