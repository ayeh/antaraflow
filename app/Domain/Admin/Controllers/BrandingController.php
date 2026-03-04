<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            'favicon' => ['nullable', 'mimes:jpg,jpeg,png,gif,svg,webp,ico,bmp', 'max:2048'],
            'login_background' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach (['logo', 'favicon', 'login_background'] as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('branding', 'public');
                PlatformSetting::setValue("{$field}_path", $path);
            }
        }

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
