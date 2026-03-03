<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Models\SmtpConfiguration;
use App\Domain\Admin\Services\SmtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SmtpController extends Controller
{
    public function __construct(
        private SmtpService $smtpService,
    ) {}

    public function index(): View
    {
        $config = $this->smtpService->getGlobalConfig();

        return view('admin.smtp.index', compact('config'));
    }

    public function updateGlobal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        SmtpConfiguration::query()->updateOrCreate(
            ['organization_id' => null],
            $validated,
        );

        return redirect()->route('admin.smtp.index')
            ->with('success', 'Global SMTP configuration saved successfully.');
    }

    public function testGlobal(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $config = $this->smtpService->getGlobalConfig();

        if (! $config) {
            return redirect()->route('admin.smtp.index')
                ->with('error', 'No global SMTP configuration found. Please save a configuration first.');
        }

        $success = $this->smtpService->testConnection($config, $request->input('test_email'));

        return redirect()->route('admin.smtp.index')
            ->with(
                $success ? 'success' : 'error',
                $success ? 'Test email sent successfully.' : 'Failed to send test email. Please check your SMTP settings.',
            );
    }

    public function orgIndex(): View
    {
        $organizations = Organization::query()
            ->withCount('members')
            ->get();

        $smtpConfigs = SmtpConfiguration::query()
            ->whereNotNull('organization_id')
            ->get()
            ->keyBy('organization_id');

        return view('admin.smtp.org', compact('organizations', 'smtpConfigs'));
    }

    public function updateOrg(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        SmtpConfiguration::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            $validated,
        );

        return redirect()->route('admin.smtp.org-index')
            ->with('success', "SMTP configuration for {$organization->name} saved successfully.");
    }

    public function testOrg(Request $request, Organization $organization): RedirectResponse
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $config = SmtpConfiguration::query()
            ->where('organization_id', $organization->id)
            ->first();

        if (! $config) {
            return redirect()->route('admin.smtp.org-index')
                ->with('error', "No SMTP configuration found for {$organization->name}.");
        }

        $success = $this->smtpService->testConnection($config, $request->input('test_email'));

        return redirect()->route('admin.smtp.org-index')
            ->with(
                $success ? 'success' : 'error',
                $success ? 'Test email sent successfully.' : 'Failed to send test email. Please check the SMTP settings.',
            );
    }
}
