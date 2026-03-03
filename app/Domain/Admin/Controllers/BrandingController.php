<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\BrandingService;
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
            'footer_text' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'custom_css' => ['nullable', 'string'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'login_background_url' => ['nullable', 'string', 'max:500'],
            'email_header_html' => ['nullable', 'string'],
            'email_footer_html' => ['nullable', 'string'],
        ]);

        foreach ($validated as $key => $value) {
            PlatformSetting::setValue($key, $value ?? '');
        }

        $this->brandingService->clearCache();

        return redirect()->route('admin.branding.index')
            ->with('success', 'Branding settings updated successfully.');
    }
}
