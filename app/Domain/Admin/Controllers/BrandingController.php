<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Requests\StoreBrandingPresetRequest;
use App\Domain\Admin\Requests\UpdateBrandingRequest;
use App\Domain\Admin\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach (['logo', 'favicon', 'login_background'] as $field) {
            if ($request->hasFile($field)) {
                $oldPath = PlatformSetting::getValue("{$field}_path", '');
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
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

    public function storePreset(StoreBrandingPresetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
        $existing = is_array($existing) ? $existing : [];

        $existing = array_filter($existing, fn ($t) => $t['name'] !== $validated['name']);
        $existing[] = $validated;

        PlatformSetting::setValue('custom_themes', json_encode(array_values($existing)));
        $this->brandingService->clearCache();

        return response()->json(['success' => true]);
    }

    public function destroyPreset(string $name): JsonResponse
    {

        $existing = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
        $existing = is_array($existing) ? $existing : [];
        $existing = array_filter($existing, fn ($t) => $t['name'] !== $name);

        PlatformSetting::setValue('custom_themes', json_encode(array_values($existing)));
        $this->brandingService->clearCache();

        return response()->json(['success' => true]);
    }
}
